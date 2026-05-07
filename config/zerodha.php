<?php

return [
    'api_key' => env('ZERODHA_API_KEY'),
    'api_secret' => env('ZERODHA_API_SECRET'),
    'login_id' => env('ZERODHA_LOGIN_ID'),
    'password' => env('ZERODHA_PASSWORD'),
    'totp_secret' => env('ZERODHA_TOTP_SECRET'),
    
    // 'redirect_url' => env('ZERODHA_REDIRECT_URL', 'http://127.0.0.1'),
];