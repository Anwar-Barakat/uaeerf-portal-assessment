<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CreateShowJumpingEntryRepositoryData extends Data
{
    public function __construct(
        public int $user_id,
        public string $cart_id,
        public string $rider_id,
        public string $horse_id,
        public int $event_id,
        public int $class_id,
        public string $event_name,
        public ?int $season_code = null,
    ) {}

    public static function fromEntryData(
        int $userId,
        string $cartId,
        ShowJumpingEntryData $data,
        ?int $seasonCode = null
    ): self {
        return new self(
            user_id: $userId,
            cart_id: $cartId,
            rider_id: $data->rider_id,
            horse_id: $data->horse_id,
            event_id: $data->event_id,
            class_id: $data->class_id,
            event_name: $data->event_name,
            season_code: $seasonCode,
        );
    }
}
