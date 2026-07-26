<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CreateRiderRenewalRepositoryData extends Data
{
    public function __construct(
        public int $user_id,
        public string $cart_id,
        public string $rider_id,
        public int $season_id,
        public int $discipline_id,
        public string $weight,
        public ?int $jumping_division_level = null,
        public ?string $competition_level = null,
    ) {}

    public static function fromRenewalData(int $userId, string $cartId, RiderRenewalData $data): self
    {
        return new self(
            user_id: $userId,
            cart_id: $cartId,
            rider_id: $data->rider_id,
            season_id: $data->season_id,
            discipline_id: $data->discipline_id,
            weight: $data->weight,
            jumping_division_level: $data->jumping_division_level,
            competition_level: $data->competition_level,
        );
    }
}
