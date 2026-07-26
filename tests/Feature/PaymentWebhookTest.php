<?php

use App\Jobs\FulfilPaidOrder;
use App\Repositories\PaymentTransactionRepository;
use App\Services\PayTabsService;
use App\Services\Soap\RegistrationsService;
use Illuminate\Support\Facades\DB;

const WEBHOOK_TEST_KEY = 'test-server-key';

beforeEach(function () {
    config()->set('services.paytabs.server_key', WEBHOOK_TEST_KEY);
});

function webhookPayload(array $overrides = []): array
{
    return array_merge([
        'tran_ref' => 'TST0000000001',
        'cart_id' => 'rider_reg_1_testcart',
        'cart_amount' => 100.00,
        'cart_currency' => 'AED',
        'tran_type' => 'Sale',
        'payment_result' => [
            'response_status' => 'A',
            'response_code' => 'G00000',
            'response_message' => 'Authorised',
        ],
    ], $overrides);
}

function postWebhook(array $payload, ?string $signature = null)
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, WEBHOOK_TEST_KEY);

    return test()->call('POST', '/api/paytabs/webhook', [], [], [], [
        'HTTP_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

/**
 * The staging tables carry a real foreign key to users, so a pending record
 * needs an owner.
 */
function ensureUser(int $id = 1): void
{
    if (!App\Models\User::find($id)) {
        createLinkedUser($id);
    }
}

function createPendingRegistration(string $cartId): void
{
    ensureUser();

    DB::table('rider_registrations')->insert([
        'user_id' => 1,
        'cart_id' => $cartId,
        'rider_name' => 'Test Rider',
        'date_of_birth' => '2000-01-01',
        'nationality' => 'ARE',
        'passport_number' => 'P123456',
        'discipline_id' => 2,
        'category_id' => 9,
        'status' => 'pending_payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * The federation identifies users by eefsys.WebUserProfile.UserId, carried on
 * the local user as federation_user_id. Registration cannot be submitted
 * without it.
 */
function createLinkedUser(int $id = 1, ?int $federationUserId = 7156): void
{
    App\Models\User::factory()->create([
        'id' => $id,
        'federation_user_id' => $federationUserId,
    ]);
}

function mockSoapRegistration(bool $result = true): void
{
    test()->mock(RegistrationsService::class, function ($mock) use ($result) {
        $mock->shouldReceive('submitPersonNewRegistration')
            ->andReturn((object) [
                'Submit_PersonNewRegistrationResult' => $result,
                'msg' => (object) ['string' => $result ? [] : ['Rejected by server']],
            ]);
    });
}

function createPendingRenewal(string $cartId): void
{
    ensureUser();

    DB::table('rider_renewals')->insert([
        'user_id' => 1,
        'cart_id' => $cartId,
        'rider_id' => 'E0022141',
        'season_id' => 13,
        'status' => 'pending_payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function mockSoapRenewal(bool $result = true): void
{
    test()->mock(RegistrationsService::class, function ($mock) use ($result) {
        $mock->shouldReceive('submitPersonRenewal')
            ->andReturn((object) [
                'Submit_PersonRenewalResult' => $result,
                'msg' => (object) ['string' => $result ? [] : ['Renewal rejected by server']],
            ]);
    });
}

test('webhook rejects request with missing signature', function () {
    $response = postWebhook(webhookPayload(), '');

    $response->assertStatus(400);
    expect(DB::table('payment_transactions')->count())->toBe(0);
});

test('webhook rejects request with invalid signature', function () {
    $response = postWebhook(webhookPayload(), 'invalid-signature');

    $response->assertStatus(400);
    expect(DB::table('payment_transactions')->count())->toBe(0);
});

test('webhook with valid signature processes successful payment', function () {
    createLinkedUser();
    mockSoapRegistration(true);
    createPendingRegistration('rider_reg_1_testcart');

    $response = postWebhook(webhookPayload());

    $response->assertOk()->assertJson(['status' => 'success']);

    $transaction = DB::table('payment_transactions')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe('success');
    expect((bool) $transaction->processed)->toBeTrue();

    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('completed');
    expect($registration->tran_ref)->toBe('TST0000000001');
});

test('webhook marks registration failed when soap rejects', function () {
    createLinkedUser();
    mockSoapRegistration(false);
    createPendingRegistration('rider_reg_1_testcart');

    $response = postWebhook(webhookPayload());

    $response->assertOk();

    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('failed');
    expect($registration->error_message)->toContain('Rejected by server');
    // The payment was taken, so the reference must be traceable for a refund.
    expect($registration->tran_ref)->toBe('TST0000000001');
});

test('captured payment is not marked processed when fulfilment fails', function () {
    createLinkedUser();
    mockSoapRegistration(false);
    createPendingRegistration('rider_reg_1_testcart');

    postWebhook(webhookPayload());

    $transaction = DB::table('payment_transactions')
        ->where('cart_id', 'rider_reg_1_testcart')->first();

    expect($transaction->status)->toBe('success')
        ->and((bool) $transaction->processed)->toBeFalse();
});

test('registration submits the federation user id, not the local users id', function () {
    createLinkedUser(id: 1, federationUserId: 7156);
    createPendingRegistration('rider_reg_1_testcart');

    $captured = null;
    $this->mock(RegistrationsService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('submitPersonNewRegistration')
            ->andReturnUsing(function (array $payload) use (&$captured) {
                $captured = $payload;

                return (object) [
                    'Submit_PersonNewRegistrationResult' => true,
                    'msg' => (object) ['string' => []],
                ];
            });
    });

    postWebhook(webhookPayload());

    expect($captured['UserId'])->toBe(7156);
});

test('registration is not submitted when the user has no federation profile', function () {
    createLinkedUser(id: 1, federationUserId: null);
    createPendingRegistration('rider_reg_1_testcart');

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldNotReceive('submitPersonNewRegistration');
    });

    postWebhook(webhookPayload());

    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('failed')
        ->and($registration->error_message)->toContain('No federation profile');

    $transaction = DB::table('payment_transactions')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect((bool) $transaction->processed)->toBeFalse();
});

test('webhook records declined payment without processing registration', function () {
    createPendingRegistration('rider_reg_1_testcart');

    $payload = webhookPayload([
        'payment_result' => [
            'response_status' => 'D',
            'response_code' => 'E001',
            'response_message' => 'Declined',
        ],
    ]);

    $response = postWebhook($payload);

    $response->assertOk()->assertJson(['status' => 'failed']);

    $transaction = DB::table('payment_transactions')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($transaction->status)->toBe('failed');

    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('pending_payment');
});

test('webhook processes successful renewal payment', function () {
    mockSoapRenewal(true);
    createPendingRenewal('rider_renewal_1_testcart');

    $response = postWebhook(webhookPayload([
        'cart_id' => 'rider_renewal_1_testcart',
        'tran_ref' => 'TST0000000002',
        'cart_amount' => 50.00,
    ]));

    $response->assertOk()->assertJson(['status' => 'success']);

    $renewal = DB::table('rider_renewals')
        ->where('cart_id', 'rider_renewal_1_testcart')->first();
    expect($renewal->status)->toBe('completed');
    expect($renewal->tran_ref)->toBe('TST0000000002');
});

test('webhook marks renewal failed when soap rejects', function () {
    mockSoapRenewal(false);
    createPendingRenewal('rider_renewal_1_testcart');

    postWebhook(webhookPayload([
        'cart_id' => 'rider_renewal_1_testcart',
        'tran_ref' => 'TST0000000003',
        'cart_amount' => 50.00,
    ]))->assertOk();

    $renewal = DB::table('rider_renewals')
        ->where('cart_id', 'rider_renewal_1_testcart')->first();
    expect($renewal->status)->toBe('failed');
    expect($renewal->error_message)->toContain('Renewal rejected by server');
});

test('duplicate webhook is skipped idempotently', function () {
    createLinkedUser();
    mockSoapRegistration(true);
    createPendingRegistration('rider_reg_1_testcart');

    postWebhook(webhookPayload())->assertOk();
    $response = postWebhook(webhookPayload());

    $response->assertOk()->assertJson(['status' => 'already_processed']);
    expect(DB::table('payment_transactions')->count())->toBe(1);
});

test('return url post redirects to get with params', function () {
    $response = $this->post('/api/payment/return', [
        'tranRef' => 'TST0000000001',
        'cartId' => 'rider_reg_1_testcart',
    ]);

    $response->assertRedirect(route('payment.return', [
        'tranRef' => 'TST0000000001',
        'cartId' => 'rider_reg_1_testcart',
    ]));
});

test('return url without cart id shows pending view', function () {
    $response = $this->get('/api/payment/return');

    $response->assertOk()->assertViewIs('payment.pending');
});

test('return url with unknown cart id shows pending view', function () {
    $response = $this->get('/api/payment/return?cartId=unknown_cart');

    $response->assertOk()->assertViewIs('payment.pending');
});

function createCapturedPayment(string $cartId = 'rider_reg_1_testcart'): void
{
    DB::table('payment_transactions')->insert([
        'tran_ref' => 'TST0000000001',
        'cart_id' => $cartId,
        'amount' => 100.00,
        'currency' => 'AED',
        'status' => 'success',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('return url shows success only when the registration completed', function () {
    createCapturedPayment();
    createPendingRegistration('rider_reg_1_testcart');
    DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')
        ->update(['status' => 'completed']);

    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart&tranRef=TST0000000001');

    $response->assertOk()
        ->assertViewIs('payment.success')
        ->assertSee('Rider Registration Confirmed');
});

test('captured payment with failed fulfilment is never shown as success', function () {
    createCapturedPayment();
    createPendingRegistration('rider_reg_1_testcart');
    DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')
        ->update(['status' => 'failed', 'error_message' => 'SOAP registration rejected: Invalid User ID']);

    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart&tranRef=TST0000000001');

    $response->assertOk()
        ->assertViewIs('payment.unfulfilled')
        ->assertSee('Payment Received')
        ->assertSee('Invalid User ID')
        ->assertDontSee('Payment Successful');
});

test('captured payment awaiting fulfilment keeps polling', function () {
    createCapturedPayment();
    createPendingRegistration('rider_reg_1_testcart');

    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart&tranRef=TST0000000001');

    $response->assertOk()
        ->assertViewIs('payment.pending')
        ->assertViewHas('shouldRefresh', true)
        ->assertSee('Completing your rider registration');
});

test('return page stops polling after the attempt cap', function () {
    $response = $this->get('/api/payment/return?cartId=unknown_cart&attempt=20');

    $response->assertOk()
        ->assertViewIs('payment.pending')
        ->assertViewHas('shouldRefresh', false)
        ->assertViewHas('refreshUrl', null)
        ->assertSee('Still Processing');
});

test('return url shows failed view for failed transaction', function () {
    DB::table('payment_transactions')->insert([
        'tran_ref' => 'TST0000000001',
        'cart_id' => 'rider_reg_1_testcart',
        'amount' => 100.00,
        'currency' => 'AED',
        'status' => 'failed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart&tranRef=TST0000000001');

    $response->assertOk()->assertViewIs('payment.failed');
});

test('a federation refusal is not retried', function () {
    createLinkedUser();
    mockSoapRegistration(false);
    createPendingRegistration('rider_reg_1_testcart');

    // Refusal is permanent: the same request earns the same answer, so the job
    // must fail outright rather than spend its attempts.
    $job = new FulfilPaidOrder('rider_reg_1_testcart', 'TST0000000001');
    $job->handle(app(PaymentTransactionRepository::class));

    expect($job->job?->hasFailed() ?? true)->toBeTrue();
});

test('a transport failure is retried rather than recorded as a rejection', function () {
    createLinkedUser();
    createPendingRegistration('rider_reg_1_testcart');

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldReceive('submitPersonNewRegistration')
            ->andThrow(new SoapFault('HTTP', 'Error Fetching http headers'));
    });

    expect(fn () => (new FulfilPaidOrder('rider_reg_1_testcart', 'TST0000000001'))
        ->handle(app(PaymentTransactionRepository::class)))
        ->toThrow(SoapFault::class);

    // The record stays pending: a network blip is not a refusal.
    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('pending_payment');
});

test('webhook refuses to fulfil when the amount does not match the configured fee', function () {
    createLinkedUser();
    createPendingRegistration('rider_reg_1_testcart');

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldNotReceive('submitPersonNewRegistration');
    });

    $response = postWebhook(webhookPayload(['cart_amount' => 1.00]));

    $response->assertOk()->assertJson(['status' => 'amount_mismatch']);

    $registration = DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')->first();
    expect($registration->status)->toBe('pending_payment');
});

test('webhook refuses to fulfil when the currency does not match', function () {
    createLinkedUser();
    createPendingRegistration('rider_reg_1_testcart');

    $response = postWebhook(webhookPayload(['cart_currency' => 'USD']));

    $response->assertOk()->assertJson(['status' => 'amount_mismatch']);
});

test('webhook acknowledges an unknown cart type without fulfilling it', function () {
    $response = postWebhook(webhookPayload(['cart_id' => 'mystery_1_abc']));

    $response->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'unknown_cart_type']);
    expect(DB::table('payment_transactions')->count())->toBe(0);
});

test('webhook acknowledges a malformed payload instead of erroring', function () {
    // A 5xx makes PayTabs redeliver the same broken callback indefinitely.
    $response = postWebhook(['cart_id' => 'rider_reg_1_testcart', 'tran_ref' => null]);

    $response->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'malformed_payload']);
});

test('concurrent duplicate webhooks create exactly one transaction', function () {
    createLinkedUser();
    mockSoapRegistration(true);
    createPendingRegistration('rider_reg_1_testcart');

    postWebhook(webhookPayload());
    $second = postWebhook(webhookPayload());

    $second->assertOk()->assertJson(['status' => 'already_processed']);
    expect(DB::table('payment_transactions')->count())->toBe(1);
});

test('return page will not disclose a transaction without its reference', function () {
    createCapturedPayment();
    createPendingRegistration('rider_reg_1_testcart');
    DB::table('rider_registrations')
        ->where('cart_id', 'rider_reg_1_testcart')
        ->update(['status' => 'completed']);

    // Guessed cart id, no reference: must not reveal the transaction.
    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart');

    $response->assertOk()
        ->assertViewIs('payment.pending')
        ->assertDontSee('TST0000000001');
});

test('return page will not disclose a transaction given a wrong reference', function () {
    createCapturedPayment();

    $response = $this->get('/api/payment/return?cartId=rider_reg_1_testcart&tranRef=TST9999999999');

    $response->assertOk()->assertViewIs('payment.pending');
});

test('cart ids are not guessable from the clock', function () {
    $a = PayTabsService::generateCartId('rider_reg', 1);
    $b = PayTabsService::generateCartId('rider_reg', 1);

    expect($a)->not->toBe($b)
        ->and($a)->toStartWith('rider_reg_1_')
        ->and(strlen(substr($a, strlen('rider_reg_1_'))))->toBe(16);
});

test('verify webhook signature accepts correct hmac of raw body', function () {
    $service = app(PayTabsService::class);
    $body = json_encode(['cart_id' => 'abc', 'tran_ref' => 'xyz']);
    $signature = hash_hmac('sha256', $body, WEBHOOK_TEST_KEY);

    expect($service->verifyWebhookSignature($body, $signature))->toBeTrue();
    expect($service->verifyWebhookSignature($body, 'wrong'))->toBeFalse();
    expect($service->verifyWebhookSignature($body . ' ', $signature))->toBeFalse();
});

test('is payment successful only for status A', function () {
    $service = app(PayTabsService::class);

    expect($service->isPaymentSuccessful(['payment_result' => ['response_status' => 'A']]))->toBeTrue();
    expect($service->isPaymentSuccessful(['payment_result' => ['response_status' => 'a']]))->toBeTrue();
    expect($service->isPaymentSuccessful(['payment_result' => ['response_status' => 'D']]))->toBeFalse();
    expect($service->isPaymentSuccessful([]))->toBeFalse();
});

test('generate cart id contains type and user id', function () {
    $cartId = PayTabsService::generateCartId('rider_reg', 42);

    expect($cartId)->toStartWith('rider_reg_42_');
    expect(PayTabsService::generateCartId('jumping', 7))->toStartWith('jumping_7_');
});
