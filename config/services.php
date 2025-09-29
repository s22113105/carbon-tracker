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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // OpenAI 設定 - 重要：絕對不要在這裡直接寫 API Key！
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),  // 不要設定預設值！
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2000),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.3),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

];