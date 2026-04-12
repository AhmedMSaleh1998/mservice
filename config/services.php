<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY', 'AIzaSyBaZBOxrXVrT2bdxdz2VbT_G4iZIygOuIY'),
    ],

    'community_sms' => [
        'enabled' => env('COMMUNITY_SMS_ENABLED', false),
        'endpoint' => env('COMMUNITY_SMS_ENDPOINT', 'https://app.community-ads.com/SendSMSAPI/api/SMSSender/SendSMSWithDLR'),
        'username' => env('COMMUNITY_SMS_USERNAME'),
        'password' => env('COMMUNITY_SMS_PASSWORD'),
        'sender' => env('COMMUNITY_SMS_SENDER'),
        'lang' => env('COMMUNITY_SMS_LANG', 'a'),
        'dlr_url' => env('COMMUNITY_SMS_DLR_URL'),
        'normalize_receivers' => env('COMMUNITY_SMS_NORMALIZE_RECEIVERS', true),
    ],

    'oracle' => [
        'export_enabled' => env('ORACLE_EXPORT_ENABLED', true),
        'register_lookup_enabled' => env('ORACLE_REGISTER_LOOKUP_ENABLED', true),
        'subscription_lookup_enabled' => env('ORACLE_SUBSCRIPTION_LOOKUP_ENABLED', false),
        'payment_sync_enabled' => env('ORACLE_PAYMENT_SYNC_ENABLED', true),
        'host' => env('ORACLE_HOST'),
        'port' => env('ORACLE_PORT', '1521'),
        'service_name' => env('ORACLE_SERVICE_NAME'),
        'charset' => env('ORACLE_CHARSET', 'AL32UTF8'),
        'driver' => env('ORACLE_DRIVER', 'oci8'),
        'username' => env('ORACLE_USERNAME'),
        'password' => env('ORACLE_PASSWORD'),
    ],

    'registration_documents' => [
        'signed_url_ttl' => env('REGISTRATION_DOCUMENTS_SIGNED_URL_TTL', 60),
    ],

    'fawry' => [
        'enabled' => env('FAWRY_ENABLED', false),
        'base_url' => env('FAWRY_BASE_URL', 'https://atfawry.fawrystaging.com'),
        'merchant_code' => env('FAWRY_MERCHANT_CODE'),
        'secure_key' => env('FAWRY_SECURE_KEY'),
        'currency_code' => env('FAWRY_CURRENCY', 'EGP'),
        'merchant_ref_prefix' => env('FAWRY_MERCHANT_REF_PREFIX'),
        'payment_method' => env('FAWRY_PAYMENT_METHOD', 'PayAtFawry'),
        'payment_expiry_minutes' => env('FAWRY_PAYMENT_EXPIRY_MINUTES'),
        'payment_expiry_hours' => env('FAWRY_PAYMENT_EXPIRY_HOURS', 1),
        'return_url' => env('FAWRY_RETURN_URL'),
        'frontend_return_url' => env('FAWRY_FRONTEND_RETURN_URL'),
        'webhook_url' => env('FAWRY_WEBHOOK_URL'),
    ],

];
