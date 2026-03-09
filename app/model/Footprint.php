<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户浏览足迹模型
 */
class Footprint extends Model
{
    protected $name = 'user_footprints';
    protected $pk = 'id';

    // 设置字段信息
    protected $schema = [
        'id'             => 'bigint',
        'user_id'        => 'bigint',
        'product_id'     => 'bigint',
        'view_count'     => 'int',
        'last_view_time' => 'datetime',
        'created_at'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false; // 使用 last_view_time 代替

    /**
     * 关联商品
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
