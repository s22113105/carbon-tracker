<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/esp32/*',  // 排除所有 ESP32 相關路由
        'api/gps',       // 舊路由（如果還在使用）
        'api/gps/*'
    ];
}