<?php

namespace App\Data;

use App\Services\Soap\CommonsService;
use Spatie\LaravelData\Data;

class RiderRegistrationData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $date_of_birth,
        public ?string $nationality,
        public string $gender_id,
        public string $nationality_id,
        public string $city_id,
        public string $country_id,
        public string $email,
        public string $mobile,
        public string $address,
        public string $po_box,
        public string $weight,
        public string $visa_category,
        public string $eid,
        public bool $register_season,
        public bool $register_fei,
        public ?string $passport_number,
        public int $discipline_id,
        public int $category_id,
    ) {}

    /**
     * Every select is checked against the federation's own list, so a code the
     * form never offered - or one it offered but disabled - cannot be posted
     * straight to the web service.
     */
    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . now()->subYears(11)->toDateString()],
            'nationality' => ['nullable', 'string', 'max:3'],
            'gender_id' => ['required', 'string', 'max:10', ...CommonsService::inRule('genders')],
            'nationality_id' => ['required', 'string', 'max:10', ...CommonsService::inRule('countries')],
            'city_id' => ['required', 'string', 'max:10', ...CommonsService::inRule('cities')],
            'country_id' => ['required', 'string', 'max:10', ...CommonsService::inRule('countries')],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'min:7', 'max:20'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'po_box' => ['required', 'string', 'max:20'],
            'weight' => ['required', 'numeric', 'min:20', 'max:200'],
            'visa_category' => ['required', 'string', 'max:10', ...CommonsService::inRule('visa_categories')],
            'eid' => [
                'required',
                'string',
                'regex:/^\d{3}-\d{4}-\d{7}-\d$/',
                function ($attribute, $value, $fail) {
                    $dobYear = substr((string) request('date_of_birth', ''), 0, 4);
                    if ($dobYear && preg_match('/^\d{3}-(\d{4})-/', $value, $matches) && $matches[1] !== $dobYear) {
                        $fail("The Emirates ID birth year must match the date of birth year ({$dobYear}).");
                    }
                },
            ],
            'register_season' => ['boolean'],
            'register_fei' => ['boolean'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'discipline_id' => ['required', 'integer', 'min:1', ...CommonsService::inRule('disciplines')],
            'category_id' => ['required', 'integer', 'min:1', ...CommonsService::inRule('categories')],
        ];
    }

    public static function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'The athlete must be at least 11 years old.',
            'eid.regex' => 'Emirates ID must match the format xxx-xxxx-xxxxxxx-x.',
        ];
    }
}
