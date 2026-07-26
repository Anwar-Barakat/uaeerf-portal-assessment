<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ShowJumpingEntryData extends Data
{
    public function __construct(
        // Federation rider/horse ids are alphanumeric (E0007711, H000103),
        // matching ClassEntriesWeb.RiderID / HorseID varchar(50).
        public string $rider_id,
        public string $horse_id,
        public int $event_id,
        public int $class_id,
        public string $event_name,
    ) {}

    public static function rules(): array
    {
        return [
            'rider_id' => ['required', 'string', 'max:50'],
            'horse_id' => ['required', 'string', 'max:50'],
            // 'numeric', not 'integer': the calendar reports RaceID zero-padded
            // ("0004289") and the integer rule rejects leading zeros.
            'event_id' => ['required', 'numeric', 'min:1'],
            'class_id' => ['required', 'numeric', 'min:0'],
            'event_name' => ['required', 'string', 'max:255'],
        ];
    }
}
