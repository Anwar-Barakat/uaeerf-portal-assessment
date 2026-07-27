<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'soap' => [
        'auth_url' => env('SOAP_AUTH_URL'),
        'registrations_url' => env('SOAP_REGISTRATIONS_URL'),
        'riders_url' => env('SOAP_RIDERS_URL'),
        'horses_url' => env('SOAP_HORSES_URL'),
        'calendar_url' => env('SOAP_CALENDAR_URL'),
        'commons_url' => env('SOAP_COMMONS_URL'),
        'common_ops_url' => env('SOAP_COMMON_OPS_URL'),
        'jumping_criteria_url' => env('SOAP_JUMPING_CRITERIA_URL'),

        'auth_username' => env('SOAP_AUTH_USERNAME'),
        'auth_password' => env('SOAP_AUTH_PASSWORD'),

        // WSHorses.SearchHorseListV5 does not respond within 120s on the dev
        // server, so horse lookups read eefsys.HorseMaster directly. Flip this
        // on once the web service is usable.
        'horses_via_webservice' => env('SOAP_HORSES_VIA_WEBSERVICE', false),

        // WSDLs are static contracts; re-fetching one per client costs ~100ms.
        'cache_wsdl' => env('SOAP_CACHE_WSDL', true),
    ],

    'federation' => [
        // Login verifies against eefsys.WebUserProfile. When MSSQL is
        // unreachable, logins fail closed unless this is explicitly enabled
        // for local development.
        'allow_local_auth_fallback' => env('FEDERATION_ALLOW_LOCAL_AUTH_FALLBACK', false),

        // Schema-qualified names of the live federation tables. Overridable so
        // tests can point them at an unqualified sqlite table.
        'user_profile_table' => env('FEDERATION_USER_PROFILE_TABLE', 'eefsys.WebUserProfile'),
        // The table the federation's own system reads. The dbo copy of the
        // same name is a near-empty scratch table - an entry written there is
        // invisible to the federation.
        'class_entries_table' => env('FEDERATION_CLASS_ENTRIES_TABLE', 'eefsys.ClassEntriesWeb'),
    ],

    'paytabs' => [
        'profile_id' => env('PAYTABS_PROFILE_ID'),
        'server_key' => env('PAYTABS_SERVER_KEY'),
        'callback_url' => env('PAYTABS_CALLBACK_URL'),
        'return_url' => env('PAYTABS_RETURN_URL'),
        'endpoint' => env('PAYTABS_ENDPOINT', 'https://secure.paytabs.com/payment/request'),
    ],

];
