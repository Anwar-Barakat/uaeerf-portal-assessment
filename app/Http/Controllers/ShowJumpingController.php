<?php

namespace App\Http\Controllers;

use App\Data\CreateShowJumpingEntryRepositoryData;
use App\Data\ShowJumpingEntryData;
use App\Models\UserProfile;
use App\Repositories\ShowJumpingEntryRepository;
use App\Services\PayTabsService;
use App\Services\Soap\CalendarService;
use App\Services\Soap\HorsesService;
use App\Services\Soap\RidersService;
use App\Services\Soap\ShowJumpingCriteriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShowJumpingController extends Controller
{
    public function __construct(
        protected PayTabsService $payTabsService,
        protected ShowJumpingCriteriaService $criteriaService,
        protected ShowJumpingEntryRepository $entryRepo,
        protected RidersService $ridersService,
        protected HorsesService $horsesService,
        protected CalendarService $calendarService
    ) {}

    /**
     * Entry form.
     *
     * Riders and horses are scoped to the stable on the user's federation
     * profile - that is the only link between a portal login and the riders and
     * horses it may enter. A profile with no stable gets empty lists and an
     * explanation rather than a form that silently cannot be submitted.
     */
    public function create(): \Inertia\Response
    {
        $stableId = (string) (UserProfile::query()
            ->where('UserId', Auth::user()->federation_user_id)
            ->value('Stable_ID') ?? '');

        return Inertia::render('jumping/entry', [
            'stableId' => $stableId,
            // Only current-season registrations can be entered - the
            // eligibility service refuses anyone else outright - so those lead
            // the list, with the stable's own riders and horses after them.
            'userRiders' => $this->safely(
                fn () => $this->mergeById(
                    $this->ridersService->ridersForCurrentSeason(),
                    $this->notSeasonRegistered($this->ridersService->ridersForStable($stableId)),
                    'rider_id'
                ),
                'riders'
            ),
            'userHorses' => $this->safely(
                fn () => $this->mergeById(
                    $this->horsesService->horsesForCurrentSeason(),
                    $this->notSeasonRegistered(
                        $stableId === '' ? [] : $this->horsesService->searchHorses(stableId: $stableId)
                    ),
                    'horse_id'
                ),
                'horses'
            ),
            // Each event carries its own classes: ClassCode is only unique
            // within an event, so the form cannot offer a single global list.
            'events' => $this->safely(
                fn () => array_map(
                    fn (array $event) => $event + [
                        'classes' => $this->calendarService->getClassesForEvent((int) $event['event_id']),
                    ],
                    $this->calendarService->getOpenEvents()
                ),
                'events'
            ),
        ]);
    }

    /**
     * Season for the chosen event, read from the calendar rather than accepted
     * from the form. Every real ClassEntriesWeb row carries one, and a
     * season-less entry risks being invisible to season-scoped views.
     */
    protected function resolveSeasonCode(int $eventId): ?int
    {
        try {
            foreach ($this->calendarService->getEventList() as $event) {
                if ((int) $event['event_id'] === $eventId) {
                    return $event['season_id'] === '' ? null : (int) $event['season_id'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not resolve season for event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Mark entries whose season registration we cannot vouch for.
     *
     * Only the season-registration view proves a current registration.
     * HorseMaster.ActiveSeason and a rider's latest-season row look like they
     * mean the same thing and do not - the eligibility service rejects plenty
     * of rows carrying them. Anything not sourced from that view is therefore
     * reported as not registered rather than optimistically selectable.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function notSeasonRegistered(array $items): array
    {
        return array_map(
            fn (array $item) => array_merge($item, ['registered_current_season' => false]),
            $items
        );
    }

    /**
     * Concatenate two lists, keeping the first occurrence of each id.
     *
     * @param  array<int, array<string, mixed>>  $preferred
     * @param  array<int, array<string, mixed>>  $additional
     * @return array<int, array<string, mixed>>
     */
    protected function mergeById(array $preferred, array $additional, string $key): array
    {
        $seen = [];
        $merged = [];

        foreach ([...$preferred, ...$additional] as $item) {
            $id = (string) ($item[$key] ?? '');

            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $merged[] = $item;
        }

        return $merged;
    }

    /**
     * One unavailable federation list must not blank the whole page.
     *
     * @return array<int, mixed>
     */
    protected function safely(callable $fetch, string $label): array
    {
        try {
            return $fetch();
        } catch (\Throwable $e) {
            Log::error("Show jumping entry: could not load {$label}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function validateEligibility(Request $request)
    {
        // 'numeric', not 'integer': the calendar reports RaceID zero-padded
        // ("0004289") and the integer rule rejects leading zeros, which failed
        // every eligibility check before it reached the federation.
        $validated = $request->validate([
            'rider_id' => 'required|string|max:50',
            'horse_id' => 'required|string|max:50',
            'event_id' => 'required|numeric',
            'class_id' => 'required|numeric',
        ]);

        $validated['event_id'] = (int) $validated['event_id'];
        $validated['class_id'] = (int) $validated['class_id'];

        try {
            $result = $this->criteriaService->validateRiderHorseCombination($validated);

            return response()->json([
                'success' => true,
                'eligible' => $result['riderEligible'] && $result['horseEligible'],
                'rider_eligible' => $result['riderEligible'],
                'horse_eligible' => $result['horseEligible'],
                'details' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Eligibility validation failed', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to validate eligibility',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function initiateEntry(ShowJumpingEntryData $data)
    {
        $user = Auth::user();
        try {
            $eligibility = $this->criteriaService->validateRiderHorseCombination([
                'rider_id' => $data->rider_id,
                'horse_id' => $data->horse_id,
                'event_id' => $data->event_id,
                'class_id' => $data->class_id,
            ]);

            if (!($eligibility['riderEligible'] && $eligibility['horseEligible'])) {
                return back()->withErrors(['eligibility' => 'Rider or horse not eligible for this competition']);
            }

        } catch (\Exception $e) {
            Log::error('Show jumping eligibility check failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['eligibility' => 'Failed to validate eligibility. Please try again.']);
        }
        $cartId = PayTabsService::generateCartId('jumping', $user->id);
        $repositoryData = CreateShowJumpingEntryRepositoryData::fromEntryData(
            $user->id,
            $cartId,
            $data,
            $this->resolveSeasonCode($data->event_id)
        );

        $this->entryRepo->create($repositoryData);
        $paymentData = $this->payTabsService->createShowJumpingPayment([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '0501234567',
            'city' => $user->city ?? 'Dubai',
            'country' => 'AE',
        ], [
            'event_name' => $data->event_name,
        ], $cartId);

        if (empty($paymentData['redirect_url'])) {
            Log::error('PayTabs jumping payment missing redirect_url', ['cart_id' => $cartId]);

            return back()->withErrors(['payment' => 'Unable to start payment. Please try again.']);
        }

        return Inertia::location($paymentData['redirect_url']);
    }

    /**
     * @return bool whether the entry reached ClassEntriesWeb
     */
    public function processEntry(string $cartId, string $tranRef): bool
    {
        $entry = $this->entryRepo->findByCartId($cartId);

        if (!$entry) {
            Log::error('Show jumping entry not found', ['cart_id' => $cartId]);

            return false;
        }

        if ($entry->status === 'completed') {
            Log::info('Show jumping entry already processed', ['cart_id' => $cartId]);

            return true;
        }

        // A previous attempt may have written the entry and then failed to
        // record it locally. Inserting again would enter the combination twice
        // for one payment.
        try {
            if ($this->entryRepo->existsForReference($tranRef)) {
                Log::warning('Show jumping entry already filed under this reference - recording it', [
                    'cart_id' => $cartId,
                    'tran_ref' => $tranRef,
                ]);

                $this->recordEntryCompleted($cartId, $tranRef);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not check for an existing entry', ['error' => $e->getMessage()]);
        }

        try {
            // The insert goes to the federation database and cannot be rolled
            // back by a local transaction, so it is not wrapped in one: holding
            // a SQLite write open across it locked the database for everything
            // else, and the failed local write then looked like a failed entry.
            $this->entryRepo->insertToClassEntriesWeb($entry, $tranRef);

            $this->recordEntryCompleted($cartId, $tranRef, [
                'rider_id' => $entry->rider_id,
                'horse_id' => $entry->horse_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Show jumping entry database insert failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            $this->entryRepo->markFailed($cartId, $e->getMessage(), $tranRef);

            return false;
        }
    }

    /**
     * Record an entry the federation already holds.
     *
     * The insert cannot be undone, so a failure to write our own copy must not
     * throw: the job would retry and enter the class a second time.
     *
     * @param  array<string, mixed>  $context
     */
    protected function recordEntryCompleted(string $cartId, string $tranRef, array $context = []): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->entryRepo->markCompleted($cartId, $tranRef);

                Log::info('Show jumping entry completed', [
                    'cart_id' => $cartId,
                    'tran_ref' => $tranRef,
                ] + $context);

                return;
            } catch (\Throwable $e) {
                Log::warning('Could not record a completed entry', [
                    'cart_id' => $cartId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                usleep(200_000 * $attempt);
            }
        }

        Log::critical('Entry accepted by the federation but not recorded locally', [
            'cart_id' => $cartId,
            'tran_ref' => $tranRef,
        ]);
    }
}
