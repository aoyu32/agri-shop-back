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
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With');
        header('Access-Control-Max-Age: 1728000');

        // 处理OPTIONS请求
        if ($request->method(true) == 'OPTIONS') {
            return response('', 204);
        }

        return $next($request);
    }
}
