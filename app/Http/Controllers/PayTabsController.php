<?php

namespace App\Http\Controllers;

use App\Data\PaymentTransactionData;
use App\Data\PaymentWebhookData;
use App\Jobs\FulfilPaidOrder;
use App\Repositories\PaymentTransactionRepository;
use App\Services\PaymentFulfilmentService;
use App\Services\PayTabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayTabsController extends Controller
{
    /**
     * How long the return page keeps polling for the webhook before it stops
     * and tells the customer to check the dashboard. 3s apart.
     */
    protected const MAX_POLL_ATTEMPTS = 20;

    public function __construct(
        protected PayTabsService $payTabsService,
        protected PaymentTransactionRepository $transactionRepo,
        protected PaymentFulfilmentService $fulfilmentService
    ) {}

    public function webhook(Request $request)
    {
        Log::info('PayTabs webhook received', [
            'cart_id' => $request->input('cart_id'),
            'tran_ref' => $request->input('tran_ref'),
        ]);
        $signature = $request->header('Signature') ?? $request->header('signature');
        if (!$signature || !$this->payTabsService->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('PayTabs webhook signature verification failed', [
                'cart_id' => $request->input('cart_id'),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }
        // A malformed payload must not become a 500: PayTabs treats a 5xx as a
        // delivery failure and redelivers the same broken callback forever.
        try {
            $webhookData = PaymentWebhookData::from($this->payTabsService->parseWebhook($request->all()));
        } catch (\Throwable $e) {
            Log::error('PayTabs webhook payload could not be parsed', [
                'cart_id' => $request->input('cart_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'malformed_payload'], 200);
        }

        $type = PayTabsService::typeFromCartId($webhookData->cart_id);

        if ($type === null) {
            Log::error('Unknown cart_id type', ['cart_id' => $webhookData->cart_id]);

            return response()->json(['status' => 'ignored', 'reason' => 'unknown_cart_type'], 200);
        }

        $transactionData = new PaymentTransactionData(
            tran_ref: $webhookData->tran_ref,
            cart_id: $webhookData->cart_id,
            amount: $webhookData->cart_amount,
            currency: $webhookData->cart_currency,
            status: $webhookData->isSuccessful() ? 'success' : 'failed',
            response_code: $webhookData->payment_result->response_code,
            response_message: $webhookData->payment_result->response_message,
            webhook_payload: (string) json_encode($request->all()),
        );

        // Atomic claim. Checking then inserting leaves a window in which two
        // concurrent redeliveries both pass the check; the loser then hits the
        // unique index and 500s, which makes PayTabs retry again.
        [$transaction, $isNew] = $this->transactionRepo->claim($transactionData);

        if (!$isNew) {
            Log::info('Duplicate webhook received, skipping', [
                'tran_ref' => $webhookData->tran_ref,
                'cart_id' => $webhookData->cart_id,
            ]);

            return response()->json(['status' => 'already_processed']);
        }

        if (!$webhookData->isSuccessful()) {
            Log::warning('PayTabs payment failed', [
                'cart_id' => $webhookData->cart_id,
                'response' => $webhookData->payment_result,
            ]);

            return response()->json(['status' => 'failed']);
        }

        if (!$this->amountMatchesExpectedFee($webhookData, $type)) {
            return response()->json(['status' => 'amount_mismatch'], 200);
        }

        // Queued: the federation call can take seconds and the gateway expects
        // a prompt acknowledgement.
        //
        // The callback is an acknowledgement that the money was captured, and
        // must succeed even when fulfilment does not. Returning 5xx here would
        // make PayTabs redeliver a payment we have already recorded. Fulfilment
        // failures are handled by the job's own retry and failed() hooks; the
        // catch only fires on the sync queue driver, where dispatch runs inline.
        try {
            FulfilPaidOrder::dispatch($webhookData->cart_id, $webhookData->tran_ref);
        } catch (\Throwable $e) {
            Log::critical('Payment captured but fulfilment failed - manual review required', [
                'cart_id' => $webhookData->cart_id,
                'tran_ref' => $webhookData->tran_ref,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Refuse to fulfil anything whose reported amount or currency does not
     * match the configured fee for that cart type.
     */
    protected function amountMatchesExpectedFee(PaymentWebhookData $webhookData, string $type): bool
    {
        $expected = PayTabsService::feeFor($type);
        $currency = (string) config('paytabs.currency');

        if ($expected === null) {
            return false;
        }

        $amountMatches = abs($webhookData->cart_amount - $expected) < 0.001;
        $currencyMatches = strtoupper($webhookData->cart_currency) === strtoupper($currency);

        if ($amountMatches && $currencyMatches) {
            return true;
        }

        Log::critical('PayTabs amount mismatch - refusing to fulfil', [
            'cart_id' => $webhookData->cart_id,
            'tran_ref' => $webhookData->tran_ref,
            'expected' => $expected . ' ' . $currency,
            'received' => $webhookData->cart_amount . ' ' . $webhookData->cart_currency,
        ]);

        return false;
    }

    public function returnUrl(Request $request)
    {
        $tranRef = $request->input('tranRef');
        $cartId = $request->input('cartId');

        // The gateway POSTs and we redirect to GET, so log only once, on the
        // GET that actually renders. The pending page then polls every 3s with
        // an attempt counter; logging each refresh would bury everything else.
        if ($request->isMethod('get') && !$request->filled('attempt')) {
            Log::info('User returned from PayTabs', [
                'tran_ref' => $tranRef,
                'cart_id' => $cartId,
            ]);
        }

        if ($request->isMethod('post')) {
            return redirect()->route('payment.return', array_filter([
                'tranRef' => $tranRef,
                'cartId' => $cartId,
            ]));
        }

        $attempt = max(0, (int) $request->input('attempt'));

        if (!$cartId) {
            return $this->pendingView('Payment processing... Please wait.', null, null, $attempt);
        }

        $transaction = $this->transactionRepo->findByCartId($cartId);

        // The webhook may not have landed yet - the customer can beat it back.
        if (!$transaction) {
            return $this->pendingView('Payment processing... Please wait.', $cartId, $tranRef, $attempt);
        }

        // This route is deliberately stateless (the gateway's cross-site POST
        // cannot be relied on to carry the session cookie), so it cannot check
        // the logged-in user. Instead it demands the transaction reference,
        // which only the payer and the gateway hold - without it a guessed
        // cart id discloses nothing.
        if (!is_string($tranRef) || !hash_equals((string) $transaction->tran_ref, $tranRef)) {
            Log::warning('Payment return rejected: cart id presented without its transaction reference', [
                'cart_id' => $cartId,
            ]);

            return $this->pendingView('Payment processing... Please wait.', $cartId, $tranRef, $attempt);
        }

        if ($transaction->status !== 'success') {
            return view('payment.failed', [
                'transaction' => $transaction,
            ]);
        }

        // Payment captured. What matters now is whether the federation
        // actually accepted the submission it paid for.
        $fulfilment = $this->fulfilmentService->forCartId($transaction->cart_id);

        if ($fulfilment['state'] === PaymentFulfilmentService::STATE_COMPLETED) {
            return view('payment.success', [
                'transaction' => $transaction,
                'fulfilment' => $fulfilment,
            ]);
        }

        if ($fulfilment['state'] === PaymentFulfilmentService::STATE_FAILED) {
            return view('payment.unfulfilled', [
                'transaction' => $transaction,
                'fulfilment' => $fulfilment,
                'supportEmail' => config('paytabs.support_email'),
            ]);
        }

        return $this->pendingView(
            'Payment received. Completing your ' . strtolower($fulfilment['type']) . '...',
            $cartId,
            $tranRef,
            $attempt
        );
    }

    /**
     * Bounded polling: keep refreshing while the webhook is in flight, then
     * stop and hand the customer their reference rather than spinning forever.
     */
    protected function pendingView(string $message, ?string $cartId, ?string $tranRef, int $attempt): \Illuminate\Contracts\View\View
    {
        $shouldRefresh = $attempt < self::MAX_POLL_ATTEMPTS;

        return view('payment.pending', [
            'message' => $message,
            'shouldRefresh' => $shouldRefresh,
            'refreshUrl' => $shouldRefresh
                ? route('payment.return', array_filter([
                    'cartId' => $cartId,
                    'tranRef' => $tranRef,
                    'attempt' => $attempt + 1,
                ]))
                : null,
        ]);
    }
}
