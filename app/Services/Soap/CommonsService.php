<?php

namespace App\Services\Soap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CommonsService extends BaseSoapClient
{
    protected function getWsdlUrl(): string
    {
        return config('services.soap.commons_url') . '?WSDL';
    }

    /**
     * SOAP responses are stdClass graphs. Serialising them into the cache store
     * and reading them back yields __PHP_Incomplete_Class, which throws on
     * property access ("tried to access a property on an incomplete object").
     * Cache plain associative arrays instead - the JSON sent to the frontend is
     * unchanged because the keys are identical.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeList(mixed $items): array
    {
        if ($items === null) {
            return [];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return array_values(array_map(
            fn ($item) => array_map(
                fn ($value) => is_scalar($value) || $value === null ? $value : (array) $value,
                (array) $item
            ),
            $items
        ));
    }

    public function getCityList(): array
    {
        return Cache::remember('soap_cities', 86400, function () {
            $result = $this->call('getCityList');
            return $this->normalizeList($result->getCityListResult->City ?? null);
        });
    }

    public function getJumpingDivisionLevelList(): array
    {
        return Cache::remember('soap_divisions', 86400, function () {
            $result = $this->call('getJumpingDivisionLevelList');
            return $this->normalizeList($result->getJumpingDivisionLevelListResult->JumpingDivisionLevel ?? null);
        });
    }

    public function getCategoryList(): array
    {
        return Cache::remember('soap_categories', 86400, function () {
            $result = $this->call('getCategoryList');
            return $this->normalizeList($result->getCategoryListResult->Category ?? null);
        });
    }

    public function getCountryList(): array
    {
        return Cache::remember('soap_countries', 86400, function () {
            $result = $this->call('getCountryList');
            return $this->normalizeList($result->getCountryListResult->Country ?? null);
        });
    }

    public function getGCCCountryList(): array
    {
        return Cache::remember('soap_gcc_countries', 86400, function () {
            $result = $this->call('getGCCCountryList');
            return $this->normalizeList($result->getGCCCountryListResult->Country ?? null);
        });
    }

    public function getDisciplineList(): array
    {
        return Cache::remember('soap_disciplines', 86400, function () {
            $result = $this->call('getDisciplineList');
            return $this->normalizeList($result->getDisciplineListResult->Discipline ?? null);
        });
    }

    public function getSeasonList(): array
    {
        return Cache::remember('soap_seasons', 86400, function () {
            $result = $this->call('getSeasonList');
            return $this->normalizeList($result->getSeasonListResult->Season ?? null);
        });
    }

    public function getGenderList(): array
    {
        return Cache::remember('soap_genders', 86400, function () {
            $result = $this->call('getGenderList');
            return $this->normalizeList($result->getGenderListResult->Gender ?? null);
        });
    }

    public function getVisaCategoryList(): array
    {
        return Cache::remember('soap_visa_categories', 86400, function () {
            $result = $this->call('getVisaCategoryList');
            return $this->normalizeList($result->getVisaCategoryListResult->VisaCategory ?? null);
        });
    }

    /**
     * The codes a select may legitimately submit, e.g. `allowedCodes('cities')`.
     *
     * Read from the cache only. The form that renders a list warms it, so by
     * the time anything is submitted the codes are known locally; when they are
     * not, this returns an empty array and the caller drops the rule rather
     * than blocking a submission on an unreachable web service - or paying for
     * a SOAP round trip in the middle of validation.
     *
     * @return array<int, string>
     */
    public function allowedCodes(string $list): array
    {
        return array_values(array_unique(array_map(
            static fn ($item) => (string) ($item['Code'] ?? ''),
            $this->cachedList($list)
        )));
    }

    /**
     * A list as last fetched, or an empty array if it has not been.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cachedList(string $list): array
    {
        $items = Cache::get('soap_' . $list);

        return is_array($items) ? $items : [];
    }

    /**
     * `Rule::in` for a select, or no rule at all when the codes are unknown.
     *
     * @return array<int, \Illuminate\Validation\Rules\In>
     */
    public static function inRule(string $list): array
    {
        try {
            $codes = array_filter(app(self::class)->allowedCodes($list));
        } catch (\Throwable) {
            // Never let the source of the list decide whether a form can be
            // submitted at all: with no codes to check against, the field falls
            // back to its type and format rules.
            return [];
        }

        return $codes === [] ? [] : [Rule::in($codes)];
    }

    public function checkIfEmailAvailable(string $email): bool
    {
        $result = $this->call('CheckIfEmailAvailable', ['email' => $email]);
        return $result->CheckIfEmailAvailableResult ?? false;
    }

    public function checkIfMobileNoAvailable(string $mobile): bool
    {
        $result = $this->call('CheckIfMobileNoAvailable', ['mobileNo' => $mobile]);
        return $result->CheckIfMobileNoAvailableResult ?? false;
    }

    public function clearCache(): void
    {
        $keys = [
            'soap_cities',
            'soap_divisions',
            'soap_categories',
            'soap_countries',
            'soap_gcc_countries',
            'soap_disciplines',
            'soap_seasons',
            'soap_genders',
            'soap_visa_categories',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
