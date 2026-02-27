<?php

declare(strict_types=1);

namespace app\common;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Exception;

/**
 * JWT 工具类
 */
class Jwt
{
    /**
     * 获取配置
     */
    private static function getConfig($key, $default = null)
    {
        return config('jwt.' . $key, $default);
    }

    /**
     * 生成 JWT Token
     * @param int $userId 用户ID
     * @param array $data 额外数据
     * @param int $expire 过期时间（秒），默认使用配置
     * @return string
     */
    public static function createToken(int $userId, array $data = [], int $expire = null): string
    {
        $time = time();
        $expire = $expire ?? self::getConfig('expire', 86400);

        $payload = [
            'iss' => self::getConfig('iss', 'agri-shop'),
            'aud' => self::getConfig('aud', 'agri-shop-client'),
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + $expire,
            'uid' => $userId,
            'data' => $data
        ];

        $key = self::getConfig('key', 'agri_shop_secret_key_2024');
        $alg = self::getConfig('alg', 'HS256');

        return FirebaseJWT::encode($payload, $key, $alg);
    }

    /**
     * 验证并解析 JWT Token
     * @param string $token
     * @return object|null 返回解析后的数据，失败返回null
     */
    public static function verifyToken(string $token): ?object
    {
        try {
            $key = self::getConfig('key', 'agri_shop_secret_key_2024');
            $alg = self::getConfig('alg', 'HS256');

            $decoded = FirebaseJWT::decode($token, new Key($key, $alg));
            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 从 Token 中获取用户ID
     * @param string $token
     * @return int|null
     */
    public static function getUserId(string $token): ?int
    {
        $decoded = self::verifyToken($token);
        return $decoded ? (int)$decoded->uid : null;
    }

    /**
     * 刷新 Token（延长过期时间）
     * @param string $token
     * @param int $expire 新的过期时间（秒）
     * @return string|null 返回新token，失败返回null
     */
    public static function refreshToken(string $token, int $expire = null): ?string
    {
        $decoded = self::verifyToken($token);
        if (!$decoded) {
            return null;
        }

        $expire = $expire ?? self::getConfig('expire', 86400);
        return self::createToken((int)$decoded->uid, (array)$decoded->data, $expire);
    }
}
