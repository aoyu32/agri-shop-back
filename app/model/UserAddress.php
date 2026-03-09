<?php

namespace app\model;

use think\Model;

class UserAddress extends Model
{
    protected $name = 'user_addresses';
    protected $pk = 'id';

    // 设置字段信息
    protected $schema = [
        'id'             => 'bigint',
        'user_id'        => 'bigint',
        'receiver_name'  => 'string',
        'receiver_phone' => 'string',
        'province_code'  => 'string',
        'province_name'  => 'string',
        'city_code'      => 'string',
        'city_name'      => 'string',
        'district_code'  => 'string',
        'district_name'  => 'string',
        'detail_address' => 'string',
        'full_address'   => 'string',
        'is_default'     => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 获取用户的所有地址
     */
    public static function getUserAddresses($userId)
    {
        return self::where('user_id', $userId)
            ->order('is_default', 'desc')
            ->order('id', 'desc')
            ->select();
    }

    /**
     * 获取用户的默认地址
     */
    public static function getDefaultAddress($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_default', 1)
            ->find();
    }

    /**
     * 设置默认地址
     */
    public static function setDefault($userId, $addressId)
    {
        // 先取消所有默认地址
        self::where('user_id', $userId)->update(['is_default' => 0]);

        // 设置新的默认地址
        return self::where('id', $addressId)
            ->where('user_id', $userId)
            ->update(['is_default' => 1]);
    }

    /**
     * 删除地址
     */
    public static function deleteAddress($userId, $addressId)
    {
        return self::where('id', $addressId)
            ->where('user_id', $userId)
            ->delete();
    }
}
