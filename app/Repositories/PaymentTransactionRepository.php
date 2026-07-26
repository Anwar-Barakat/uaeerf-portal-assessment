<?php

namespace App\Repositories;

use App\Data\PaymentTransactionData;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

class PaymentTransactionRepository
{
    public function create(PaymentTransactionData $data): PaymentTransaction
    {
        return PaymentTransaction::create($data->toArray());
    }

    /**
     * Atomically claim a transaction reference.
     *
     * Webhook redeliveries can arrive concurrently; a read-then-insert leaves a
     * race where both pass the duplicate check and the loser violates the
     * unique index. firstOrCreate resolves it in one statement.
     *
     * @return array{0: PaymentTransaction, 1: bool} the row, and whether this call created it
     */
    public function claim(PaymentTransactionData $data): array
    {
        $attributes = $data->toArray();

        $transaction = PaymentTransaction::firstOrCreate(
            ['tran_ref' => $data->tran_ref],
            $attributes
        );

        return [$transaction, $transaction->wasRecentlyCreated];
    }

    /**
     * Captured payments whose fulfilment never completed. These are the refund
     * or retry candidates.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PaymentTransaction>
     */
    public function unfulfilled(): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentTransaction::where('status', 'success')
            ->where('processed', false)
            ->orderBy('created_at')
            ->get();
    }

    public function findByCartId(string $cartId): ?PaymentTransaction
    {
        return PaymentTransaction::where('cart_id', $cartId)->first();
    }

    public function findByTranRef(string $tranRef): ?PaymentTransaction
    {
        return PaymentTransaction::where('tran_ref', $tranRef)->first();
    }

    public function markProcessed(string $cartId): bool
    {
        return PaymentTransaction::where('cart_id', $cartId)
            ->update([
                'processed' => true,
                'processed_at' => now(),
            ]) > 0;
    }

    public function isProcessed(string $cartId): bool
    {
        $transaction = $this->findByCartId($cartId);
        return $transaction && $transaction->processed;
    }

    public function getRecentTransactions(int $limit = 50): array
    {
        return PaymentTransaction::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getSuccessfulTransactions(int $limit = 50): array
    {
        return PaymentTransaction::where('status', 'success')
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
