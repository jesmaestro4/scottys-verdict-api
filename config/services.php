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

    'marketcheck' => [
        'base_url' => env('MARKETCHECK_BASE_URL', 'https://api.marketcheck.com/v2'),
        'key' => env('MARKETCHECK_API_KEY'),
        'secret' => env('MARKETCHECK_API_SECRET'),
        'verify_ssl' => env('MARKETCHECK_VERIFY_SSL', true),
    ],

    'car_images' => [
        'base_url' => env('CAR_IMAGES_BASE_URL', 'https://carimagesapi.com'),
        'key' => env('CAR_IMAGES_API_KEY'),
        'secret' => env('CAR_IMAGES_API_SECRET'),
        'verify_ssl' => env('CAR_IMAGES_VERIFY_SSL', true),
    ],

    'vpic' => [
        'base_url' => env('VPIC_BASE_URL', 'https://vpic.nhtsa.dot.gov/api'),
        'verify_ssl' => env('VPIC_VERIFY_SSL', true),
    ],

    'contact' => [
        'to_email' => env('CONTACT_TO_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'to_name' => env('CONTACT_TO_NAME', env('APP_NAME', 'Scotty Says Autos')),
    ],

    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', true),
        'secret' => env('RECAPTCHA_SECRET'),
        'expected_action' => env('RECAPTCHA_EXPECTED_ACTION', 'contact'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

];
