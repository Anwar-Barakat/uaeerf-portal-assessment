<?php

namespace App\Repositories;

use App\Data\CreateRiderRepositoryData;
use App\Models\RiderRegistration;
use Illuminate\Support\Facades\DB;

class RiderRegistrationRepository
{
    public function create(CreateRiderRepositoryData $data): RiderRegistration
    {
        return RiderRegistration::create($data->toArray());
    }

    public function findByCartId(string $cartId): ?RiderRegistration
    {
        return RiderRegistration::where('cart_id', $cartId)->first();
    }

    public function updateStatus(string $cartId, string $status, array $additionalData = []): bool
    {
        return RiderRegistration::where('cart_id', $cartId)
            ->update(array_merge([
                'status' => $status,
            ], $additionalData)) > 0;
    }

    public function markCompleted(string $cartId, string $tranRef, ?string $soapResponse = null): bool
    {
        return $this->updateStatus($cartId, 'completed', [
            'tran_ref' => $tranRef,
            'soap_response' => $soapResponse,
            'completed_at' => now(),
            // Clear any rejection from an earlier attempt: a completed record
            // carrying "Invalid User ID" reads as a failure to anyone looking.
            'error_message' => null,
        ]);
    }

    /**
     * The transaction reference is recorded on failure too - the payment was
     * taken, so support needs it to trace or refund.
     */
    public function markFailed(string $cartId, string $errorMessage, ?string $tranRef = null): bool
    {
        return $this->updateStatus($cartId, 'failed', array_filter([
            'error_message' => $errorMessage,
            'tran_ref' => $tranRef,
        ]));
    }

    public function findByUserId(int $userId, int $limit = 10): array
    {
        return RiderRegistration::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * @template TReturn
     *
     * @param  \Closure(\Illuminate\Database\Connection): TReturn  $callback
     * @return TReturn
     */
    public function processWithTransaction(\Closure $callback)
    {
        return DB::transaction($callback);
    }
}
