<?php

namespace App\Console\Commands;

use App\Jobs\FulfilPaidOrder;
use App\Repositories\PaymentTransactionRepository;
use Illuminate\Console\Command;

/**
 * Recovery path for payments that were captured but never fulfilled - for
 * example when the federation web service rejected the submission or was down.
 *
 * Without this, a customer who paid and got nothing has to be fixed by hand in
 * the database.
 */
class RetryUnfulfilledPayments extends Command
{
    protected $signature = 'payments:retry-unfulfilled
                            {--cart= : Retry a single cart id}
                            {--dry-run : List what would be retried without dispatching}';

    protected $description = 'Re-dispatch fulfilment for captured payments that never completed';

    public function handle(PaymentTransactionRepository $transactionRepo): int
    {
        $transactions = $transactionRepo->unfulfilled();

        if ($cart = $this->option('cart')) {
            $transactions = $transactions->where('cart_id', $cart);
        }

        if ($transactions->isEmpty()) {
            $this->info('No unfulfilled payments.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cart ID', 'Transaction Ref', 'Amount', 'Captured'],
            $transactions->map(fn ($t) => [
                $t->cart_id,
                $t->tran_ref,
                $t->currency . ' ' . number_format((float) $t->amount, 2),
                (string) $t->created_at,
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry run - nothing dispatched.');

            return self::SUCCESS;
        }

        foreach ($transactions as $transaction) {
            FulfilPaidOrder::dispatch($transaction->cart_id, $transaction->tran_ref);
        }

        $this->info("Dispatched {$transactions->count()} fulfilment job(s).");

        return self::SUCCESS;
    }
}
