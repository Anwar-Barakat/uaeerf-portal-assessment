<?php

namespace App\Data;

use App\Services\Soap\CommonsService;
use Spatie\LaravelData\Data;

class RiderRenewalData extends Data
{
    /** Discipline code for Show Jumping, per getDisciplineList(). */
    public const DISCIPLINE_SHOW_JUMPING = 2;

    public function __construct(
        public string $rider_id,
        public int $season_id,
        // DivisionID on Submit_PersonRenewal; the service rejects the
        // submission outright without it.
        public int $discipline_id,
        // "Please enter a valid weight in kilogram" - required for every
        // discipline.
        public string $weight,
        // Show jumping only: without these the service answers "Invalid
        // DIvision Level Level" and "Invalid Category", and then fails to
        // deserialise an empty competition level into an Integer.
        public ?int $jumping_division_level = null,
        public ?string $competition_level = null,
    ) {}

    /**
     * Selects are checked against the federation's own lists - see
     * RiderRegistrationData::rules().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $requiredForShowJumping = 'required_if:discipline_id,' . self::DISCIPLINE_SHOW_JUMPING;

        return [
            'rider_id' => ['required', 'string', 'max:20'],
            'season_id' => ['required', 'integer', 'min:1', ...CommonsService::inRule('seasons')],
            'discipline_id' => ['required', 'integer', 'min:1', ...CommonsService::inRule('disciplines')],
            'weight' => ['required', 'numeric', 'min:20', 'max:250'],
            'jumping_division_level' => [$requiredForShowJumping, 'nullable', 'integer', 'min:1', ...CommonsService::inRule('divisions')],
            'competition_level' => [
                $requiredForShowJumping,
                'nullable',
                'string',
                'max:20',
                ...CommonsService::inRule('categories'),
                self::categoryMatchesDivisionLevel(...),
            ],
        ];
    }

    /**
     * A category belongs to one jumping division level, and getCategoryList()
     * says which. "Young Rider" is a Division 1 grade and "Junior" a Division 2
     * one; the endurance grades and the officials' roles belong to neither and
     * are never a rider's competition level.
     */
    public static function categoryMatchesDivisionLevel(string $attribute, mixed $value, \Closure $fail): void
    {
        $divisionLevel = request('jumping_division_level');

        if ($value === null || $value === '' || $divisionLevel === null || $divisionLevel === '') {
            return;
        }

        $categories = app(CommonsService::class)->cachedList('categories');

        if ($categories === []) {
            return;
        }

        foreach ($categories as $category) {
            if ((string) ($category['Code'] ?? '') === (string) $value) {
                if ((string) ($category['DivisionLevel'] ?? '') !== (string) $divisionLevel) {
                    $fail('The selected category does not belong to the chosen division level.');
                }

                return;
            }
        }
    }
}
