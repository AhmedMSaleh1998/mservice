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

    'oracle' => [
        'host' => env('ORACLE_HOST'),
        'port' => env('ORACLE_PORT', '1521'),
        'service_name' => env('ORACLE_SERVICE_NAME'),
        'charset' => env('ORACLE_CHARSET', 'AL32UTF8'),
        'username' => env('ORACLE_USERNAME'),
        'password' => env('ORACLE_PASSWORD'),
    ],

];
