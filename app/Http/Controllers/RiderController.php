<?php

namespace App\Http\Controllers;

use App\Data\CreateRiderRepositoryData;
use App\Data\CreateRiderRenewalRepositoryData;
use App\Data\RiderRegistrationData;
use App\Data\RiderRenewalData;
use App\Exceptions\FederationRejectedException;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\RiderRegistrationRepository;
use App\Repositories\RiderRenewalRepository;
use App\Services\PayTabsService;
use App\Services\Soap\RegistrationsService;
use App\Services\Soap\RidersService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RiderController extends Controller
{
    public function __construct(
        protected PayTabsService $payTabsService,
        protected RegistrationsService $registrationsService,
        protected RiderRegistrationRepository $registrationRepo,
        protected RiderRenewalRepository $renewalRepo,
        protected RidersService $ridersService
    ) {}

    /**
     * Registration form.
     *
     * Eligibility to submit is resolved up front so the page can say so
     * plainly, rather than letting someone complete a long form and only
     * discover at the payment step that the federation will refuse them.
     */
    public function create(): \Inertia\Response
    {
        $commons = app(\App\Services\Soap\CommonsService::class);
        $user = Auth::user();

        return Inertia::render('rider/registration', [
            'disciplines' => $commons->getDisciplineList(),
            'categories' => $commons->getCategoryList(),
            'cities' => $commons->getCityList(),
            'countries' => $commons->getCountryList(),
            'genders' => $commons->getGenderList(),
            'visaCategories' => $commons->getVisaCategoryList(),
            'submissionBlockedReason' => $this->submissionBlockedReason($user),
        ]);
    }

    /**
     * Renewal form.
     *
     * The user's own stable leads the rider list, then the rest of the
     * renewable directory. It is around 180 riders in total, so the page ships
     * the whole set once and the combobox filters locally - a request per
     * keystroke buys nothing at this size.
     */
    public function renewalForm(): \Inertia\Response
    {
        $commons = app(\App\Services\Soap\CommonsService::class);
        $stableId = (string) (UserProfile::query()
            ->where('UserId', Auth::user()->federation_user_id)
            ->value('Stable_ID') ?? '');

        // The stable's own riders lead the list; the rest of the directory
        // follows so a rider kept at another stable can still be renewed.
        $riders = [];

        try {
            $stableRiders = $this->ridersService->ridersForStable($stableId);
            $seen = array_flip(array_column($stableRiders, 'rider_id'));

            $riders = [
                ...array_map(
                    fn (array $rider) => array_merge($rider, ['from_stable' => true]),
                    $stableRiders
                ),
                ...array_values(array_map(
                    fn (array $rider) => array_merge($rider, ['from_stable' => false]),
                    array_filter(
                        $this->ridersService->allRenewableRiders(),
                        fn (array $rider) => !isset($seen[$rider['rider_id']])
                    )
                )),
            ];
        } catch (\Throwable $e) {
            Log::warning('Could not load riders for renewal', ['error' => $e->getMessage()]);
        }

        // Both blockers are knowable before payment, so the riders the
        // federation would refuse never reach the form. Only if the lookup
        // itself fails does the full list stand - the pre-payment guard still
        // refuses a bad submission, so nobody is charged either way.
        $hiddenRiders = 0;

        try {
            $renewable = $this->renewableRiders($riders);
            $hiddenRiders = count($riders) - count($renewable);
            $riders = $renewable;
        } catch (\Throwable $e) {
            Log::warning('Could not load renewal blockers', ['error' => $e->getMessage()]);
        }

        return Inertia::render('rider/renewal', [
            // Only the season the federation is renewing into. The rest of the
            // list is history: every application on file is for this season,
            // and the service ignores the season on the request anyway.
            'seasons' => $this->renewableSeasons($commons),
            'disciplines' => $commons->getDisciplineList(),
            'divisionLevels' => $commons->getJumpingDivisionLevelList(),
            'categories' => $commons->getCategoryList(),
            'stableId' => $stableId,
            'riders' => $riders,
            // Said plainly on the form, so a short list does not read as riders
            // having gone missing.
            'hiddenRiders' => $hiddenRiders,
        ]);
    }

    /**
     * Only the riders the federation would accept a renewal for.
     *
     * @param  array<int, array<string, mixed>>  $riders
     * @return array<int, array<string, mixed>>
     */
    protected function renewableRiders(array $riders): array
    {
        $inFlight = $this->ridersService->ridersWithRenewalInFlight();
        $seasons = $this->ridersService->seasonsByRider();
        $current = $this->ridersService->currentSeasonCode();

        return array_values(array_filter(
            $riders,
            fn (array $rider) => $this->ridersService->renewalBlocker(
                (string) $rider['rider_id'], $inFlight, $seasons, $current
            ) === null
        ));
    }

    /**
     * The seasons a renewal may be submitted for.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function renewableSeasons(\App\Services\Soap\CommonsService $commons): array
    {
        try {
            $current = $this->ridersService->currentSeasonCode();
        } catch (\Throwable $e) {
            Log::warning('Could not determine the renewable season', ['error' => $e->getMessage()]);

            // Better the full list than an empty select the user cannot get
            // past; the pre-payment guard still refuses a bad submission.
            return $commons->getSeasonList();
        }

        return array_values(array_filter(
            $commons->getSeasonList(),
            fn (array $season) => (int) ($season['Code'] ?? 0) === $current
        ));
    }

    /**
     * Why this user cannot submit a rider registration, or null if they can.
     */
    protected function submissionBlockedReason(?User $user): ?string
    {
        if (!$user?->federation_user_id) {
            return 'Your account is not linked to the federation portal yet, so registrations cannot be '
                . 'submitted. Sign in with your federation portal account, or contact the federation.';
        }

        try {
            if (!$this->registrationsService->acceptsUserId((int) $user->federation_user_id)) {
                return 'Your federation account is not authorised to submit rider registrations. '
                    . 'Please contact the federation to have it enabled. You will not be charged.';
            }
        } catch (\Throwable $e) {
            Log::warning('Could not check federation submission eligibility', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function initiateRegistration(RiderRegistrationData $data)
    {
        $user = Auth::user();

        if (!$user->federation_user_id) {
            Log::error('Registration blocked: user has no federation profile', ['user_id' => $user->id]);

            return back()->withErrors([
                'payment' => 'Your account is not linked to the federation portal yet. Please contact support before paying.',
            ]);
        }

        // Refuse before taking money, not after. The federation only accepts
        // registrations for users on its own accepted list; anyone else is
        // guaranteed an "Invalid User ID" refusal once the payment is captured.
        if (!$this->registrationsService->acceptsUserId((int) $user->federation_user_id)) {
            Log::error('Registration blocked: federation will not accept this user', [
                'user_id' => $user->id,
                'federation_user_id' => $user->federation_user_id,
            ]);

            return back()->withErrors([
                'payment' => 'Your federation account is not yet authorised to submit registrations. '
                    . 'Please contact the federation before paying.',
            ]);
        }

        try {
            $data->nationality = $this->resolveNationalityCode($data->nationality_id);
        } catch (\Exception $e) {
            return back()->withErrors(['nationality_id' => 'Could not resolve the selected nationality. Please pick it again.']);
        }

        $cartId = PayTabsService::generateCartId('rider_reg', $user->id);
        $repositoryData = CreateRiderRepositoryData::fromRegistrationData(
            $user->id,
            $cartId,
            $data
        );

        $this->registrationRepo->create($repositoryData);
        $paymentData = $this->payTabsService->createRiderRegistrationPayment([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '0501234567',
            'city' => $user->city ?? 'Dubai',
            'country' => 'AE',
        ], $cartId);

        if (empty($paymentData['redirect_url'])) {
            Log::error('PayTabs registration payment missing redirect_url', ['cart_id' => $cartId]);

            return back()->withErrors(['payment' => 'Unable to start payment. Please try again.']);
        }

        return Inertia::location($paymentData['redirect_url']);
    }

    public function initiateRenewal(RiderRenewalData $data)
    {
        $user = Auth::user();

        if (!$user->federation_user_id) {
            Log::error('Renewal blocked: user has no federation profile', ['user_id' => $user->id]);

            return back()->withErrors([
                'payment' => 'Your account is not linked to the federation portal yet. Please contact support before paying.',
            ]);
        }

        // The two conditions the federation refuses on are readable straight
        // from its database, so check them first: no SOAP round trip, and it
        // catches an application submitted by a different portal account, which
        // the per-user service call does not report.
        try {
            $blocked = $this->ridersService->renewalBlocker(
                $data->rider_id,
                $this->ridersService->ridersWithRenewalInFlight(),
                $this->ridersService->seasonsByRider(),
                $this->ridersService->currentSeasonCode(),
            );

            if ($blocked !== null) {
                Log::info('Renewal blocked before payment', [
                    'rider_id' => $data->rider_id,
                    'reason' => $blocked,
                ]);

                return back()->withErrors(['rider_id' => $blocked . '. You have not been charged.']);
            }
        } catch (\Throwable $e) {
            Log::warning('Renewal blocker lookup unavailable', ['error' => $e->getMessage()]);
        }

        // Backstop: ask the service itself, in case it refuses for a reason the
        // database does not record. Discovering that after payment leaves a
        // refund to chase.
        try {
            $blocked = $this->registrationsService->renewalBlockedReason(
                $data->rider_id,
                $data->season_id,
                $data->discipline_id,
                (int) $user->federation_user_id,
                $data->jumping_division_level,
                $data->competition_level,
            );

            if ($blocked !== null) {
                Log::info('Renewal blocked before payment', [
                    'rider_id' => $data->rider_id,
                    'season_id' => $data->season_id,
                    'reason' => $blocked,
                ]);

                return back()->withErrors(['rider_id' => $blocked . ' You have not been charged.']);
            }
        } catch (\Throwable $e) {
            // An unavailable check must not block a legitimate renewal; the
            // post-payment path still records the outcome honestly.
            Log::warning('Renewal eligibility check unavailable', ['error' => $e->getMessage()]);
        }

        $cartId = PayTabsService::generateCartId('rider_renewal', $user->id);
        $repositoryData = CreateRiderRenewalRepositoryData::fromRenewalData($user->id, $cartId, $data);
        $this->renewalRepo->create($repositoryData);
        $paymentData = $this->payTabsService->createRiderRenewalPayment([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '0501234567',
            'city' => $user->city ?? 'Dubai',
            'country' => 'AE',
        ], $cartId);

        if (empty($paymentData['redirect_url'])) {
            Log::error('PayTabs renewal payment missing redirect_url', ['cart_id' => $cartId]);

            return back()->withErrors(['payment' => 'Unable to start payment. Please try again.']);
        }

        return Inertia::location($paymentData['redirect_url']);
    }

    /**
     * Resolve the 3-letter nationality code the federation expects.
     *
     * Deliberately throws rather than defaulting to 'ARE': a wrong nationality
     * is submitted to the federation as fact and cannot be told apart from a
     * real one afterwards. Failing here stops the payment before it is taken.
     */
    protected function resolveNationalityCode(string $nationalityId): string
    {
        foreach (app(\App\Services\Soap\CommonsService::class)->getCountryList() as $country) {
            if ((string) ($country['Code'] ?? '') === $nationalityId) {
                $short = strtoupper(trim((string) ($country['ShortName'] ?? '')));

                if ($short === '') {
                    break;
                }

                return substr($short, 0, 3);
            }
        }

        Log::error('Nationality code resolution failed', ['nationality_id' => $nationalityId]);

        throw new \RuntimeException("Unknown nationality_id {$nationalityId}");
    }

    /**
     * @return bool whether the federation accepted the registration
     */
    public function processRegistration(string $cartId, string $tranRef): bool
    {
        $registration = $this->registrationRepo->findByCartId($cartId);

        if (!$registration) {
            Log::error('Rider registration not found', ['cart_id' => $cartId]);

            return false;
        }

        if ($registration->status === 'completed') {
            Log::info('Rider registration already processed', ['cart_id' => $cartId]);

            return true;
        }

        // The federation identifies users by eefsys.WebUserProfile.UserId, not
        // by the local users.id. Sending the local key is what the web service
        // rejects with "Invalid User ID".
        $federationUserId = User::find($registration->user_id)?->federation_user_id;

        if (!$federationUserId) {
            Log::error('Rider registration has no federation user id', [
                'cart_id' => $cartId,
                'user_id' => $registration->user_id,
            ]);

            $this->registrationRepo->markFailed($cartId, 'No federation profile linked to this account', $tranRef);

            return false;
        }

        try {
            // Outside a transaction on purpose: a SQLite write transaction held
            // open across the web service call locks the database for everyone
            // else, and a failure to write the result then reads as a failed
            // submission - see recordRenewalCompleted().
            $soapResult = $this->registrationsService->submitPersonNewRegistration([
                'PayType' => 'ONLINE',
                'UserId' => (int) $federationUserId,
                'Name' => $registration->rider_name,
                'FatherName' => $registration->last_name
                    ?: (str_contains($registration->rider_name, ' ')
                        ? substr($registration->rider_name, strrpos($registration->rider_name, ' ') + 1)
                        : $registration->rider_name),
                'Dob' => date('c', strtotime($registration->date_of_birth)),
                'Nationality' => $registration->nationality,
                'NationalityID' => (string) $registration->nationality_id,
                'SexID' => (string) $registration->gender_id,
                'CityID' => (string) $registration->city_id,
                'CountryID' => (string) $registration->country_id,
                'Email' => (string) $registration->email,
                'Mobile' => (string) $registration->mobile,
                'Address' => (string) $registration->address,
                'PoBox' => (string) $registration->po_box,
                'Weight' => (string) $registration->weight,
                'VisaCategory' => (int) $registration->visa_category,
                'EID' => (string) $registration->eid,
                'RegisterSeason' => $registration->register_season ? 1 : 0,
                'RegisterFEI' => $registration->register_fei ? 1 : 0,
                'RefNum' => $registration->passport_number,
                'DivisionId' => (int) $registration->discipline_id,
                'Category' => (string) $registration->category_id,
                'ReferenceNumber' => $tranRef,
                'DateSubmit' => date('c'),
            ]);

            $messages = array_map('strval', (array) ($soapResult->msg->string ?? []));

            if (empty($soapResult->Submit_PersonNewRegistrationResult) && !$this->referenceAlreadyFiled($messages)) {
                throw new FederationRejectedException($messages);
            }

            $this->recordCompleted(
                fn (?string $encoded) => $this->registrationRepo->markCompleted($cartId, $tranRef, $encoded),
                'Rider registration',
                $cartId,
                $tranRef,
                $soapResult
            );

            return true;

        } catch (FederationRejectedException $e) {
            // Refused on its merits - the same request will be refused again.
            Log::error('Rider registration rejected by the federation', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            $this->registrationRepo->markFailed($cartId, $e->getMessage(), $tranRef);

            return false;
        } catch (\Throwable $e) {
            // Transport or unexpected failure: leave the record pending and let
            // the caller retry rather than recording a false rejection.
            Log::error('Rider registration SOAP call failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * "Reference number exist." means our own earlier submission is on file.
     *
     * The reference is one payment's transaction ref, so the service refusing
     * it as a duplicate is confirmation that the work was done, not a failure.
     *
     * @param  array<int, string>  $messages
     */
    protected function referenceAlreadyFiled(array $messages): bool
    {
        foreach ($messages as $message) {
            if (str_contains(mb_strtolower($message), 'reference number exist')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record something the federation has accepted.
     *
     * The submission cannot be undone, so losing this write strands work that
     * has actually gone through - which is what a locked database did once: the
     * renewal was filed, the local write failed, the job retried, and the user
     * was told their paid renewal had not completed. Retry the write instead of
     * throwing, because throwing sends the job back to submit a second time.
     *
     * @param  \Closure(?string): mixed  $write
     */
    protected function recordCompleted(\Closure $write, string $label, string $cartId, string $tranRef, object $soapResult): void
    {
        $encoded = json_encode($soapResult) ?: null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $write($encoded);

                Log::info($label . ' completed', ['cart_id' => $cartId, 'tran_ref' => $tranRef]);

                return;
            } catch (\Throwable $e) {
                Log::warning('Could not record a completed ' . mb_strtolower($label), [
                    'cart_id' => $cartId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                usleep(200_000 * $attempt);
            }
        }

        // The federation has it; only our copy is behind.
        Log::critical($label . ' accepted by the federation but not recorded locally', [
            'cart_id' => $cartId,
            'tran_ref' => $tranRef,
        ]);
    }

    /**
     * @return bool whether the federation accepted the renewal
     */
    public function processRenewal(string $cartId, string $tranRef): bool
    {
        $renewal = $this->renewalRepo->findByCartId($cartId);

        if (!$renewal) {
            Log::error('Rider renewal not found', ['cart_id' => $cartId]);

            return false;
        }

        if ($renewal->status === 'completed') {
            Log::info('Rider renewal already processed', ['cart_id' => $cartId]);

            return true;
        }

        // A previous attempt may have reached the federation and then failed to
        // record it locally. Submitting again would file a second application
        // for a single payment.
        try {
            if ($this->ridersService->renewalExistsForReference($tranRef)) {
                Log::warning('Rider renewal already filed under this reference - recording it', [
                    'cart_id' => $cartId,
                    'tran_ref' => $tranRef,
                ]);

                $this->renewalRepo->markCompleted($cartId, $tranRef, '{"recovered":true}');

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not check for an existing renewal', ['error' => $e->getMessage()]);
        }

        try {
            // Deliberately outside any database transaction. Holding a SQLite
            // write transaction open across a five-second web service call
            // locked the database for everything else ("database is locked"),
            // and the failure to write the result then looked like a failed
            // submission - so the job retried one the federation had already
            // accepted.
            $soapResult = $this->registrationsService->submitPersonRenewal([
                'PayType' => 'ONLINE',
                // Both are required: omitting either draws "Invalid User ID"
                // and "Invalid Division ID" regardless of the rest.
                'UserId' => (int) (User::find($renewal->user_id)?->federation_user_id ?? 0),
                'DivisionID' => (int) $renewal->discipline_id,
                'PersonID' => (string) $renewal->rider_id,
                'SeasonCode' => (int) $renewal->season_id,
                'Weight' => (string) $renewal->weight,
                'RegisterSeason' => 1,
                'ReferenceNumber' => $tranRef,
                'DateSubmit' => date('c'),
                // Show jumping only; harmless defaults elsewhere.
                'JumpingDivisionLevel' => (int) ($renewal->jumping_division_level ?? 0),
                'Complevel' => (string) ($renewal->competition_level ?? ''),
            ]);

            $messages = array_map('strval', (array) ($soapResult->msg->string ?? []));

            if (empty($soapResult->Submit_PersonRenewalResult) && !$this->referenceAlreadyFiled($messages)) {
                throw new FederationRejectedException($messages);
            }

            $this->recordCompleted(
                fn (?string $encoded) => $this->renewalRepo->markCompleted($cartId, $tranRef, $encoded),
                'Rider renewal',
                $cartId,
                $tranRef,
                $soapResult
            );

            return true;

        } catch (FederationRejectedException $e) {
            Log::error('Rider renewal rejected by the federation', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            $this->renewalRepo->markFailed($cartId, $e->getMessage(), $tranRef);

            return false;
        } catch (\Throwable $e) {
            Log::error('Rider renewal SOAP call failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
