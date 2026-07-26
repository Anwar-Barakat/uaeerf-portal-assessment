<?php

namespace App\Jobs;

use App\Http\Controllers\RiderController;
use App\Http\Controllers\ShowJumpingController;
use App\Repositories\PaymentTransactionRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fulfils a captured payment: submits to the federation, then marks the
 * transaction processed.
 *
 * Runs on the queue so the PayTabs webhook can answer in milliseconds. The
 * gateway retries callbacks it considers timed out, and a retried callback
 * that arrives mid-SOAP would duplicate work.
 */
class FulfilPaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public string $cartId,
        public string $tranRef
    ) {}

    public function handle(PaymentTransactionRepository $transactionRepo): void
    {
        $fulfilled = match (true) {
            str_starts_with($this->cartId, 'rider_reg_') => app(RiderController::class)
                ->processRegistration($this->cartId, $this->tranRef),
            str_starts_with($this->cartId, 'rider_renewal_') => app(RiderController::class)
                ->processRenewal($this->cartId, $this->tranRef),
            str_starts_with($this->cartId, 'jumping_') => app(ShowJumpingController::class)
                ->processEntry($this->cartId, $this->tranRef),
            default => throw new \RuntimeException("Unknown cart_id type: {$this->cartId}"),
        };

        // A false return means the federation refused the submission on its
        // merits. Retrying would send the identical request and get the
        // identical refusal, so fail immediately instead of burning five
        // attempts over twenty minutes. Transport failures throw instead, and
        // those the queue does retry.
        if (!$fulfilled) {
            $this->fail(new \RuntimeException(
                "Federation refused fulfilment for cart {$this->cartId} - not retryable"
            ));

            return;
        }

        $transactionRepo->markProcessed($this->cartId);

        Log::info('Paid order fulfilled', [
            'cart_id' => $this->cartId,
            'tran_ref' => $this->tranRef,
        ]);
    }

    /**
     * Terminal failure: the customer has been charged and has nothing to show
     * for it. 'processed' stays false so the transaction is picked up by
     * `payments:retry-unfulfilled` and shows as needing review.
     */
    public function failed(?\Throwable $e): void
    {
        Log::critical('Payment captured but fulfilment failed - manual review required', [
            'cart_id' => $this->cartId,
            'tran_ref' => $this->tranRef,
            'error' => $e?->getMessage(),
        ]);
    }
}
