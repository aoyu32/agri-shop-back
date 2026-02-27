<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 购物车模型
 */
class Cart extends Model
{
    protected $name = 'shopping_cart';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联商品
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * 关联规格
     */
    public function spec()
    {
        return $this->belongsTo(ProductSpec::class, 'spec_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
