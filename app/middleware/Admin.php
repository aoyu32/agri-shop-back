<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use app\common\Response;
use app\model\User;

/**
 * 管理员权限校验中间件
 */
class Admin
{
    /**
     * 处理请求
     */
    public function handle($request, Closure $next)
    {
        $userId = $request->userId ?? 0;
        if (!$userId) {
            return Response::unauthorized('未登录或登录已失效');
        }

        $user = User::find($userId);
        if (!$user || $user->role !== 'admin') {
            return Response::forbidden('您没有管理员权限');
        }

        $request->adminUser = $user;

        return $next($request);
    }
}
