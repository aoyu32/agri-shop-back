<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户收藏模型
 */
class Favorite extends Model
{
    protected $name = 'user_favorites';
    protected $pk = 'id';

    // 设置字段信息
    protected $schema = [
        'id'          => 'bigint',
        'user_id'     => 'bigint',
        'product_id'  => 'bigint',
        'created_at'  => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

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
