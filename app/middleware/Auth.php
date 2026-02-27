<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use app\common\Response;
use app\common\Jwt;

/**
 * JWT 认证中间件
 */
class Auth
{
    /**
     * 处理请求
     */
    public function handle($request, Closure $next)
    {
        // 获取 token
        $token = $request->header('Authorization');
        $token = str_replace('Bearer ', '', $token);

        if (!$token) {
            return Response::unauthorized('未提供token');
        }

        // 验证 token
        $userId = Jwt::getUserId($token);
        if (!$userId) {
            return Response::unauthorized('token已过期或无效');
        }

        // 将用户ID存入请求对象，方便后续使用
        $request->userId = $userId;

        return $next($request);
    }
}
