<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fees
    |--------------------------------------------------------------------------
    |
    | Single source of truth for what each flow costs. Used both when creating
    | the hosted payment page and when verifying the amount reported back by
    | the webhook, so a tampered or mismatched callback cannot be fulfilled.
    |
    | Keyed by cart_id prefix.
    |
    */

    'currency' => env('PAYTABS_CURRENCY', 'AED'),

    'fees' => [
        'rider_reg' => 100.00,
        'rider_renewal' => 50.00,
        'jumping' => 150.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Support contact
    |--------------------------------------------------------------------------
    |
    | Shown to a customer whose payment succeeded but whose federation
    | submission did not, so they have a real route to a refund. Leave null to
    | hide the link rather than promise a contact that does not exist.
    |
    */

    'support_email' => env('PAYTABS_SUPPORT_EMAIL'),

    'timeout' => env('PAYTABS_TIMEOUT', 20),

    'connect_timeout' => env('PAYTABS_CONNECT_TIMEOUT', 10),

    'retry_times' => env('PAYTABS_RETRY_TIMES', 2),

    'retry_sleep_ms' => env('PAYTABS_RETRY_SLEEP_MS', 250),

];
