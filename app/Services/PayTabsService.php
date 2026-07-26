<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTabsService
{
    protected string $endpoint;
    protected string $profileId;
    protected string $serverKey;
    protected string $callbackUrl;
    protected string $returnUrl;

    public function __construct()
    {
        // Cast rather than declare string properties over raw env(): a missing
        // key would otherwise be a TypeError at container-resolution time,
        // surfacing as an unrelated 500 far from the real cause.
        $this->endpoint = (string) config('services.paytabs.endpoint');
        $this->profileId = (string) config('services.paytabs.profile_id');
        $this->serverKey = (string) config('services.paytabs.server_key');
        $this->callbackUrl = (string) config('services.paytabs.callback_url');
        $this->returnUrl = (string) config('services.paytabs.return_url');
    }

    /**
     * Fee for a cart type, e.g. 'rider_reg'. Shared by payment creation and
     * webhook verification so the two can never disagree.
     */
    public static function feeFor(string $type): ?float
    {
        $fee = config("paytabs.fees.{$type}");

        return $fee === null ? null : (float) $fee;
    }

    /**
     * Derive the cart type from a cart id such as "rider_reg_9_6a65cbaf0a6f1".
     */
    public static function typeFromCartId(string $cartId): ?string
    {
        foreach (array_keys((array) config('paytabs.fees')) as $type) {
            if (str_starts_with($cartId, $type . '_')) {
                return $type;
            }
        }

        return null;
    }

    public function createRiderRegistrationPayment(array $userData, string $cartId): array
    {
        return $this->createPayment([
            'cart_id' => $cartId,
            'cart_description' => 'Rider Registration',
            'cart_amount' => self::feeFor('rider_reg'),
            'cart_currency' => config('paytabs.currency'),
            'customer_details' => [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'street1' => $userData['address'] ?? 'N/A',
                'city' => $userData['city'] ?? 'Dubai',
                'state' => $userData['state'] ?? 'Dubai',
                'country' => $userData['country'] ?? 'AE',
                'zip' => $userData['zip'] ?? '00000',
            ],
        ]);
    }

    public function createRiderRenewalPayment(array $userData, string $cartId): array
    {
        return $this->createPayment([
            'cart_id' => $cartId,
            'cart_description' => 'Rider Renewal',
            'cart_amount' => self::feeFor('rider_renewal'),
            'cart_currency' => config('paytabs.currency'),
            'customer_details' => [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'street1' => $userData['address'] ?? 'N/A',
                'city' => $userData['city'] ?? 'Dubai',
                'state' => $userData['state'] ?? 'Dubai',
                'country' => $userData['country'] ?? 'AE',
                'zip' => $userData['zip'] ?? '00000',
            ],
        ]);
    }

    public function createShowJumpingPayment(array $userData, array $entryData, string $cartId): array
    {
        return $this->createPayment([
            'cart_id' => $cartId,
            'cart_description' => sprintf(
                'Show Jumping Entry - %s',
                $entryData['event_name'] ?? 'Competition'
            ),
            'cart_amount' => self::feeFor('jumping'),
            'cart_currency' => config('paytabs.currency'),
            'customer_details' => [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'street1' => $userData['address'] ?? 'N/A',
                'city' => $userData['city'] ?? 'Dubai',
                'state' => $userData['state'] ?? 'Dubai',
                'country' => $userData['country'] ?? 'AE',
                'zip' => $userData['zip'] ?? '00000',
            ],
        ]);
    }

    protected function createPayment(array $data): array
    {
        $payload = [
            'profile_id' => $this->profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $data['cart_id'],
            'cart_description' => $data['cart_description'],
            'cart_currency' => $data['cart_currency'],
            'cart_amount' => $data['cart_amount'],
            'callback' => $this->callbackUrl,
            'return' => $this->returnUrl,
            'customer_details' => $data['customer_details'],
        ];

        Log::info('PayTabs payment request', [
            'cart_id' => $data['cart_id'],
            'amount' => $data['cart_amount'],
        ]);

        // Bounded: without a timeout a stalled gateway holds the customer's
        // request open until PHP's own limit kills it.
        $response = Http::withHeaders([
            'Authorization' => $this->serverKey,
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout((int) config('paytabs.connect_timeout'))
            ->timeout((int) config('paytabs.timeout'))
            ->retry(
                (int) config('paytabs.retry_times'),
                (int) config('paytabs.retry_sleep_ms'),
                throw: false
            )
            ->post($this->endpoint, $payload);

        if (!$response->successful()) {
            Log::error('PayTabs payment creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to create payment: ' . $response->body());
        }

        $result = $response->json();

        Log::info('PayTabs payment created', [
            'cart_id' => $data['cart_id'],
            'tran_ref' => $result['tran_ref'] ?? null,
            'redirect_url' => $result['redirect_url'] ?? null,
        ]);

        return $result;
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $calculatedSignature = hash_hmac('sha256', $rawBody, $this->serverKey);

        return hash_equals($calculatedSignature, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'tran_ref' => $payload['tran_ref'] ?? null,
            'cart_id' => $payload['cart_id'] ?? null,
            'cart_amount' => $payload['cart_amount'] ?? null,
            'cart_currency' => $payload['cart_currency'] ?? null,
            'tran_type' => $payload['tran_type'] ?? null,
            'payment_result' => [
                'response_status' => $payload['payment_result']['response_status'] ?? null,
                'response_code' => $payload['payment_result']['response_code'] ?? null,
                'response_message' => $payload['payment_result']['response_message'] ?? null,
            ],
            'customer_details' => $payload['customer_details'] ?? [],
        ];
    }

    public function isPaymentSuccessful(array $webhookData): bool
    {
        return isset($webhookData['payment_result']['response_status'])
            && strtoupper($webhookData['payment_result']['response_status']) === 'A';
    }

    /**
     * uniqid() is derived from the clock and is therefore guessable. The cart
     * id travels in URLs and identifies a transaction, so the suffix uses real
     * randomness.
     */
    public static function generateCartId(string $type, int $userId): string
    {
        return sprintf('%s_%d_%s', $type, $userId, bin2hex(random_bytes(8)));
    }
}
