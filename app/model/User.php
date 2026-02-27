<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户模型
 */
class User extends Model
{
    // 设置表名
    protected $name = 'users';

    // 设置字段信息
    protected $schema = [
        'id'          => 'bigint',
        'username'    => 'string',
        'password'    => 'string',
        'phone'       => 'string',
        'avatar'      => 'string',
        'nickname'    => 'string',
        'gender'      => 'int',
        'role'        => 'string',
        'status'      => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 隐藏字段
    protected $hidden = ['password'];

    // 类型转换
    protected $type = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 密码修改器 - 自动加密
     */
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     */
    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * 根据用户名或手机号查找用户
     */
    public static function findByUsernameOrPhone($account)
    {
        return self::where('username', $account)
            ->whereOr('phone', $account)
            ->find();
    }

    /**
     * 根据手机号查找用户
     */
    public static function findByPhone($phone)
    {
        return self::where('phone', $phone)->find();
    }
}
