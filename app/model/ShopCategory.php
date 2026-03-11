<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 店铺分类关联模型
 */
class ShopCategory extends Model
{
    protected $name = 'shop_categories';

    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'shop_id'     => 'int',
        'category_id' => 'int',
        'sort'        => 'int',
        'created_at'  => 'string',
        'updated_at'  => 'string',
    ];

    // 关联分类
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // 关联店铺
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
