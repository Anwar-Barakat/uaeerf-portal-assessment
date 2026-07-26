<?php

namespace App\Services\Soap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HorsesService extends BaseSoapClient
{
    protected function getWsdlUrl(): string
    {
        return config('services.soap.horses_url') . '?WSDL';
    }

    /**
     * Horses available to a stable, shaped for the entry form.
     *
     * HorseID is alphanumeric (H000103), matching ClassEntriesWeb.HorseID
     * varchar(50) - never coerce it to an integer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchHorses(string $stableId = '', string $name = '', string $eefId = ''): array
    {
        // SearchHorseListV5 did not return within 120s on the dev server for
        // any filter combination tested, and once answered with a non-XML body
        // after 235s. Reading HorseMaster directly is therefore the default -
        // the brief grants MSSQL access for exactly this reason. The web
        // service path stays available behind a flag for when it is fixed.
        if (!config('services.soap.horses_via_webservice', false)) {
            return $this->searchViaDatabase($stableId, $name, $eefId);
        }

        try {
            return $this->searchViaWebService($stableId, $name, $eefId);
        } catch (\Throwable $e) {
            Log::warning('WSHorses unavailable, reading HorseMaster directly', [
                'stable_id' => $stableId,
                'error' => $e->getMessage(),
            ]);

            return $this->searchViaDatabase($stableId, $name, $eefId);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchViaWebService(string $stableId, string $name, string $eefId): array
    {
        $result = $this->call('SearchHorseListV5', [
            'HorseID' => '',
            'AdminUserID' => '',
            'StableID' => $stableId,
            'SearchName' => $name,
            'SearchFEIID' => '',
            'SearchEEFID' => $eefId,
            'SearchOwner' => '',
            'SearchTrainer' => '',
            'SearchStable' => '',
            'SearchNFPassport' => '',
            'SearchMicrochip' => '',
            'SearchDiscipline' => '',
            'SearchDisciplineID' => '',
            'SearchActive' => '',
            'msg' => [],
        ]);

        $horses = $result->SearchHorseListV5Result->Horses ?? [];
        $horses = is_array($horses) ? $horses : [$horses];

        return array_values(array_map(fn ($horse) => [
            'horse_id' => (string) ($horse->HorseID ?? ''),
            'name' => trim((string) ($horse->HorseName ?? '')) ?: (string) ($horse->HorseID ?? ''),
            'stable' => (string) ($horse->Stable ?? ''),
            'stable_id' => (string) ($horse->StableID ?? ''),
            'owner' => (string) ($horse->Owner ?? ''),
            'trainer' => (string) ($horse->Trainer ?? ''),
            'gender' => (string) ($horse->Gender ?? ''),
            'registered_current_season' => (bool) ($horse->IsRegisteredInCurrentSeason ?? false),
            'at_rest' => (bool) ($horse->IsAtRest ?? false),
            'active' => (bool) ($horse->Active ?? false),
        ], $horses));
    }

    /**
     * Horses registered for the current season in a discipline.
     *
     * The eligibility service refuses anything else with "Horse is not
     * registered for the current season", so these are the only horses that can
     * actually be entered. HorseMaster's Active/ActiveSeason flags do not carry
     * that meaning - the season registration does.
     *
     * @return array<int, array<string, mixed>>
     */
    public function horsesForCurrentSeason(string $discipline = 'Show Jumping'): array
    {
        return Cache::remember('federation_season_horses_' . $discipline, 900, function () use ($discipline) {
            $season = app(RidersService::class)->currentSeasonCode();

            return DB::connection('federation')
                ->table('eefsys.VIEW_Registrations_WithUserID')
                ->select('ID')
                ->selectRaw('MAX(Name) AS Name, MAX(Stable_ID) AS Stable_ID')
                ->where('Registrant', 'Horse')
                ->where('Discipline', $discipline)
                ->where('SeasonCode', $season)
                ->whereNotNull('ID')
                // One row per horse: grouping on Name and Stable_ID too listed
                // a horse once per stable or spelling, and duplicate options
                // break the combobox (see RidersService::allRenewableRiders).
                ->groupBy('ID')
                ->orderByRaw('MAX(Name)')
                ->limit(500)
                ->get()
                ->map(fn ($horse) => [
                    'horse_id' => (string) $horse->ID,
                    'name' => trim((string) $horse->Name) ?: (string) $horse->ID,
                    'stable' => '',
                    'stable_id' => (string) $horse->Stable_ID,
                    'owner' => '',
                    'trainer' => '',
                    'gender' => '',
                    'registered_current_season' => true,
                    'at_rest' => false,
                    'active' => true,
                ])
                ->all();
        });
    }

    /**
     * Fallback source: [EEF].[eefsys].[HorseMaster].
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchViaDatabase(string $stableId, string $name, string $eefId): array
    {
        $query = DB::connection('federation')
            ->table('eefsys.HorseMaster')
            ->select('HorseID', 'Name', 'CurrentName', 'StableID', 'OwnerID', 'TrainerID', 'Active', 'ActiveSeason')
            ->where('Active', 1)
            // A handful of rows carry a placeholder name; they are unusable in
            // a picker.
            ->whereNotIn(DB::raw('LTRIM(RTRIM(ISNULL(Name, \'\')))'), ['', '-'])
            ->orderBy('Name')
            ->limit(500);

        if ($stableId !== '') {
            $query->where('StableID', $stableId);
        }

        if ($name !== '') {
            $query->where('Name', 'like', '%' . $name . '%');
        }

        if ($eefId !== '') {
            $query->where('HorseID', $eefId);
        }

        return $query->get()->map(fn ($horse) => [
            'horse_id' => (string) $horse->HorseID,
            'name' => trim((string) ($horse->CurrentName ?: $horse->Name)) ?: (string) $horse->HorseID,
            'stable' => '',
            'stable_id' => (string) $horse->StableID,
            'owner' => (string) $horse->OwnerID,
            'trainer' => (string) $horse->TrainerID,
            'gender' => '',
            'registered_current_season' => (bool) $horse->ActiveSeason,
            // HorseMaster carries LastRestPeriod but not a current at-rest
            // flag; eligibility is confirmed by ShowJumpingCriteria anyway.
            'at_rest' => false,
            'active' => (bool) $horse->Active,
        ])->all();
    }
}
