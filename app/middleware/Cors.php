<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;

/**
 * 跨域中间件
 */
class Cors
{
    /**
     * 处理请求
     */
    public function handle($request, Closure $next)
    {
        // 处理OPTIONS预检请求
        if ($request->method(true) == 'OPTIONS') {
            return response()
                ->code(204)
                ->header([
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With',
                    'Access-Control-Max-Age' => '1728000',
                ]);
        }

        $response = $next($request);

        // 添加CORS响应头
        $response->header([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With',
            'Access-Control-Max-Age' => '1728000',
        ]);

        return $response;
    }
}
