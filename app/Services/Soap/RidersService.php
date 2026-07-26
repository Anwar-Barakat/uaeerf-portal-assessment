<?php

namespace App\Services\Soap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RidersService extends BaseSoapClient
{
    protected function getWsdlUrl(): string
    {
        return config('services.soap.riders_url') . '?WSDL';
    }

    /**
     * Search by whatever the user typed.
     *
     * The service matches first and family name separately, so a single search
     * box has to try both - otherwise typing a first name never matches
     * anything.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByName(string $query): array
    {
        $byFamilyName = $this->searchRiders(name: $query);

        if ($byFamilyName !== []) {
            return $byFamilyName;
        }

        return $this->searchRiders(firstName: $query);
    }

    public function searchRiders(string $name = '', string $eefId = '', string $stableId = '', string $firstName = ''): array
    {
        $result = $this->call('SearchRiderList', [
            'SearchFirstName' => $firstName,
            'SearchLastName' => $name,
            'SearchFEIID' => '',
            'SearchEEFID' => $eefId,
            'SearchStable' => $stableId,
            'SearchNationality' => '',
            'SearchGender' => '',
            'msg' => [],
        ]);

        $riders = $result->SearchRiderListResult->EEFRider ?? [];
        $riders = is_array($riders) ? $riders : [$riders];

        return array_map(fn ($rider) => [
            'rider_id' => $rider->RiderID ?? null,
            'name' => trim(($rider->FirstName ?? '') . ' ' . ($rider->FamilyName ?? ''))
                ?: ($rider->FullName ?? $rider->RiderID ?? ''),
            'dob' => isset($rider->DOB) ? substr($rider->DOB, 0, 10) : null,
            'nationality' => $rider->NATIONALITY_short ?? null,
            'registered_current_season' => (bool) ($rider->IsRegisteredInCurrentSeason ?? false),
        ], $riders);
    }

    /**
     * Riders attached to a stable.
     *
     * SearchRiderList's SearchStable parameter returns nothing for any value
     * tried (stable id or name), so this reads the federation view instead.
     * Name and EEF-id searches still go through the web service, which works.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ridersForStable(string $stableId, ?string $discipline = null): array
    {
        if ($stableId === '') {
            return [];
        }

        return Cache::remember(
            'federation_riders_' . $stableId . '_' . ($discipline ?? 'all'),
            900,
            fn () => $this->queryRidersForStable($stableId, $discipline)
        );
    }

    /**
     * Riders registered for the current season in a discipline.
     *
     * Competition entry requires a current-season registration - the
     * eligibility service refuses anyone else with "Rider is not registered for
     * the current season". Offering only a stable's riders is therefore not
     * enough: a stable may have none registered this season.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ridersForCurrentSeason(string $discipline = 'Show Jumping'): array
    {
        return Cache::remember('federation_season_riders_' . $discipline, 900, function () use ($discipline) {
            $season = $this->currentSeasonCode();

            return DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->select('ID')
                ->selectRaw('MAX(Name) AS Name, MAX(Stable_ID) AS Stable_ID')
                ->where('Registrant', 'Rider')
                ->where('Discipline', $discipline)
                ->where('SeasonCode', $season)
                ->whereNotNull('ID')
                // One row per rider - see allRenewableRiders().
                ->groupBy('ID')
                ->orderByRaw('MAX(Name)')
                ->limit(500)
                ->get()
                ->map(fn ($rider) => [
                    'rider_id' => (string) $rider->ID,
                    'name' => trim((string) $rider->Name) ?: (string) $rider->ID,
                    'stable_id' => (string) $rider->Stable_ID,
                    'dob' => null,
                    'nationality' => null,
                    'registered_current_season' => true,
                ])
                ->all();
        });
    }

    /**
     * The season the federation is actually operating in.
     *
     * Not MAX(SeasonCode): a later season can exist with a handful of stray
     * rows (season 14 holds a single horse registration) long before it is the
     * live one. The highest season carrying a real volume of registrations is
     * the one the eligibility service treats as current.
     */
    /**
     * Every rider the federation has a registration record for.
     *
     * Renewal needs the full renewable population, not just one stable's
     * riders: the whole directory is ~100 people, so it loads once and the
     * form filters it locally rather than round-tripping per keystroke.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allRenewableRiders(): array
    {
        return Cache::remember('federation_all_riders', 900, function () {
            $season = $this->currentSeasonCode();

            // Grouped by ID alone. Grouping on Name and Stable_ID as well
            // returned the same rider two or three times - once per stable he
            // has been kept at, or per spelling of his name - which both
            // cluttered the list and broke the combobox: two options sharing a
            // value collide, and a click could land on the copy that was
            // missing the "cannot be renewed" flag.
            return DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->select('ID')
                ->selectRaw('MAX(Name) AS Name, MAX(Stable_ID) AS Stable_ID, MAX(SeasonCode) AS LatestSeason')
                ->where('Registrant', 'Rider')
                ->whereNotNull('ID')
                ->groupBy('ID')
                ->orderByRaw('MAX(Name)')
                ->get()
                ->map(fn ($rider) => [
                    'rider_id' => (string) $rider->ID,
                    'name' => trim((string) $rider->Name) ?: (string) $rider->ID,
                    'stable_id' => (string) $rider->Stable_ID,
                    'dob' => null,
                    'nationality' => null,
                    'registered_current_season' => (int) $rider->LatestSeason === $season,
                ])
                ->all();
        });
    }

    /**
     * Seasons each rider already holds a registration for.
     *
     * @return array<string, array<int, int>>
     */
    public function seasonsByRider(): array
    {
        return Cache::remember('federation_rider_seasons', 900, function () {
            return DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->select('ID', 'SeasonCode')
                ->where('Registrant', 'Rider')
                ->whereNotNull('ID')
                ->whereNotNull('SeasonCode')
                ->distinct()
                ->get()
                ->groupBy('ID')
                ->map(fn ($rows) => $rows->pluck('SeasonCode')->map(fn ($s) => (int) $s)->values()->all())
                ->all();
        });
    }

    /**
     * Riders with a renewal application already in flight, whoever submitted it.
     *
     * Submit_PersonRenewal refuses these with "There is already a pending
     * application for the Rider" - and it means *any* application, not just the
     * signed-in user's. Get_PersonRenewal only lists one user's own, so a rider
     * whose application belongs to another portal account looked renewable and
     * was refused at the payment step. This view holds them all.
     *
     * @return array<int, string>
     */
    public function ridersWithRenewalInFlight(): array
    {
        return Cache::remember('federation_renewals_in_flight', 300, function () {
            return DB::connection('federation')
                ->table('eefsys.VIEW_WEB_RiderRenewal')
                ->where('SeasonCode', $this->currentSeasonCode())
                ->whereNotNull('RiderID')
                ->distinct()
                ->pluck('RiderID')
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * Has a renewal already been filed under this payment reference?
     *
     * The reference is the PayTabs transaction ref, unique to one payment, so a
     * match means our own earlier submission landed. Fulfilment checks this
     * before submitting: a retry - after a local write failed, say - must not
     * file the application a second time.
     */
    public function renewalExistsForReference(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        return DB::connection('federation')
            ->table('eefsys.VIEW_WEB_RiderRenewal')
            ->where('ReferenceNumber', $reference)
            ->exists();
    }

    /**
     * Why this rider cannot be renewed, or null if they can.
     *
     * Both conditions are rider-level, not season-level. The service compares
     * against the season it is currently renewing into and ignores the season
     * on the request: asking to renew a rider registered for 2022-2023 into
     * 2020-2021 is still refused with "Athelete already register for the season
     * 2022-2023".
     *
     * @param  array<int, string>  $inFlight
     * @param  array<string, array<int, int>>  $seasons
     */
    public function renewalBlocker(string $riderId, array $inFlight, array $seasons, int $currentSeason): ?string
    {
        if (in_array($riderId, $inFlight, true)) {
            return 'A renewal application is already in progress for this rider';
        }

        if (in_array($currentSeason, $seasons[$riderId] ?? [], true)) {
            return 'Already registered for the current season';
        }

        return null;
    }

    public function currentSeasonCode(int $minimumRegistrations = 50): int
    {
        return (int) Cache::remember(
            'federation_current_season',
            86400,
            fn () => DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->select('SeasonCode')
                ->groupBy('SeasonCode')
                ->havingRaw('COUNT(*) >= ?', [$minimumRegistrations])
                ->orderByDesc('SeasonCode')
                ->value('SeasonCode')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function queryRidersForStable(string $stableId, ?string $discipline): array
    {
        $query = DB::connection('federation')
            ->table('eefsys.VIEW_Registrations_WithUserID')
            ->select('ID')
            ->selectRaw('MAX(Name) AS Name, MAX(SeasonCode) AS LatestSeason')
            ->where('Registrant', 'Rider')
            ->where('Stable_ID', $stableId)
            ->whereNotNull('ID')
            // One row per rider - see allRenewableRiders().
            ->groupBy('ID')
            ->orderByRaw('MAX(Name)')
            ->limit(500);

        if ($discipline !== null) {
            $query->where('Discipline', $discipline);
        }

        // Scanning the whole view for the latest season takes seconds; it
        // changes once a year.
        $currentSeason = (int) Cache::remember(
            'federation_current_season',
            86400,
            fn () => DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->max('SeasonCode')
        );

        return $query->get()->map(fn ($rider) => [
            'rider_id' => (string) $rider->ID,
            'name' => trim((string) $rider->Name) ?: (string) $rider->ID,
            'dob' => null,
            'nationality' => null,
            'registered_current_season' => (int) $rider->LatestSeason === $currentSeason,
        ])->all();
    }
}
