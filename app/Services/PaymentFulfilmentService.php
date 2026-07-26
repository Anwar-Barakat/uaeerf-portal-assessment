<?php

namespace App\Services;

use App\Repositories\RiderRegistrationRepository;
use App\Repositories\RiderRenewalRepository;
use App\Repositories\ShowJumpingEntryRepository;

/**
 * Resolves what happened *after* the money was taken.
 *
 * A captured payment is not the same thing as a completed registration: the
 * federation web service can still reject the submission. The payment views
 * report both so a customer is never told they are registered when they are
 * not.
 */
class PaymentFulfilmentService
{
    public const STATE_COMPLETED = 'completed';

    public const STATE_PENDING = 'pending';

    public const STATE_FAILED = 'failed';

    public const STATE_UNKNOWN = 'unknown';

    public function __construct(
        protected RiderRegistrationRepository $registrationRepo,
        protected RiderRenewalRepository $renewalRepo,
        protected ShowJumpingEntryRepository $entryRepo
    ) {}

    /**
     * @return array{state: string, type: string, error: string|null}
     */
    public function forCartId(string $cartId): array
    {
        [$type, $record] = match (true) {
            str_starts_with($cartId, 'rider_reg_') => ['Rider Registration', $this->registrationRepo->findByCartId($cartId)],
            str_starts_with($cartId, 'rider_renewal_') => ['Rider Renewal', $this->renewalRepo->findByCartId($cartId)],
            str_starts_with($cartId, 'jumping_') => ['Show Jumping Entry', $this->entryRepo->findByCartId($cartId)],
            default => ['Order', null],
        };

        if (!$record) {
            return ['state' => self::STATE_UNKNOWN, 'type' => $type, 'error' => null];
        }

        $state = match ($record->status) {
            'completed' => self::STATE_COMPLETED,
            'failed' => self::STATE_FAILED,
            default => self::STATE_PENDING,
        };

        return [
            'state' => $state,
            'type' => $type,
            'error' => $state === self::STATE_FAILED ? $record->error_message : null,
        ];
    }
}
