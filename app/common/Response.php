<?php

declare(strict_types=1);

namespace app\common;

/**
 * 统一响应类
 */
class Response
{
    /**
     * 成功响应
     */
    public static function success($data = [], $message = '操作成功', $code = 200)
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 失败响应
     */
    public static function error($message = '操作失败', $code = 400, $data = [])
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 未授权响应
     */
    public static function unauthorized($message = '未授权访问')
    {
        return self::error($message, 401);
    }

    /**
     * 禁止访问响应
     */
    public static function forbidden($message = '禁止访问')
    {
        return self::error($message, 403);
    }

    /**
     * 未找到响应
     */
    public static function notFound($message = '资源不存在')
    {
        return self::error($message, 404);
    }

    /**
     * 验证失败响应
     */
    public static function validateError($message = '验证失败', $errors = [])
    {
        return json([
            'code' => 422,
            'message' => $message,
            'data' => $errors,
            'timestamp' => time()
        ]);
    }
}
