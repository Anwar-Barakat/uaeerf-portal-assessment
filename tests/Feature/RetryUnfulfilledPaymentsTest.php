<?php

use App\Jobs\FulfilPaidOrder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

function insertTransaction(string $cartId, string $tranRef, string $status, bool $processed): void
{
    DB::table('payment_transactions')->insert([
        'tran_ref' => $tranRef,
        'cart_id' => $cartId,
        'amount' => 100.00,
        'currency' => 'AED',
        'status' => $status,
        'processed' => $processed,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('captured but unfulfilled payments are re-dispatched', function () {
    Bus::fake();

    insertTransaction('rider_reg_1_stuck', 'TST0000000001', 'success', false);
    insertTransaction('rider_reg_1_done', 'TST0000000002', 'success', true);
    insertTransaction('rider_reg_1_declined', 'TST0000000003', 'failed', false);

    $this->artisan('payments:retry-unfulfilled')
        ->expectsOutputToContain('Dispatched 1 fulfilment job(s).')
        ->assertSuccessful();

    // Only the captured-but-unfulfilled one qualifies: a processed payment is
    // already done, and a declined one was never owed anything.
    Bus::assertDispatchedTimes(FulfilPaidOrder::class, 1);
    Bus::assertDispatched(FulfilPaidOrder::class,
        fn (FulfilPaidOrder $job) => $job->cartId === 'rider_reg_1_stuck');
});

test('dry run dispatches nothing', function () {
    Bus::fake();

    insertTransaction('rider_reg_1_stuck', 'TST0000000001', 'success', false);

    $this->artisan('payments:retry-unfulfilled --dry-run')->assertSuccessful();

    Bus::assertNotDispatched(FulfilPaidOrder::class);
});

test('a single cart can be targeted', function () {
    Bus::fake();

    insertTransaction('rider_reg_1_a', 'TST0000000001', 'success', false);
    insertTransaction('rider_reg_1_b', 'TST0000000002', 'success', false);

    $this->artisan('payments:retry-unfulfilled --cart=rider_reg_1_b')->assertSuccessful();

    Bus::assertDispatchedTimes(FulfilPaidOrder::class, 1);
    Bus::assertDispatched(FulfilPaidOrder::class,
        fn (FulfilPaidOrder $job) => $job->cartId === 'rider_reg_1_b');
});

test('reports cleanly when there is nothing to retry', function () {
    Bus::fake();

    $this->artisan('payments:retry-unfulfilled')
        ->expectsOutputToContain('No unfulfilled payments.')
        ->assertSuccessful();

    Bus::assertNotDispatched(FulfilPaidOrder::class);
});
