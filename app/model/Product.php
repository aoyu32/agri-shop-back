<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品模型
 */
class Product extends Model
{
    // 设置表名
    protected $name = 'products';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 类型转换
    protected $type = [
        'price' => 'float',
        'original_price' => 'float',
        'stock' => 'integer',
        'sales' => 'integer',
        'is_hot' => 'integer',
        'is_promotion' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联店铺
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 关联商品图片
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->order('sort_order', 'asc');
    }

    /**
     * 关联商品规格
     */
    public function specs()
    {
        return $this->hasMany(ProductSpec::class, 'product_id')->where('status', 1)->order('sort_order', 'asc');
    }

    /**
     * 关联商品标签
     */
    public function tags()
    {
        return $this->hasMany(ProductTag::class, 'product_id');
    }

    /**
     * 获取热销商品
     */
    public static function getHotProducts($limit = 10)
    {
        return self::where('is_hot', 1)
            ->where('status', 'on_sale')
            ->order('sales', 'desc')
            ->limit((int)$limit)
            ->select();
    }

    /**
     * 获取促销商品
     */
    public static function getPromotionProducts($limit = 10)
    {
        return self::where('is_promotion', 1)
            ->where('status', 'on_sale')
            ->order('sales', 'desc')
            ->limit((int)$limit)
            ->select();
    }

    /**
     * 根据分类获取商品
     */
    public static function getProductsByCategory($categoryId, $page = 1, $pageSize = 20)
    {
        return self::where('category_id', $categoryId)
            ->where('status', 'on_sale')
            ->order('sales', 'desc')
            ->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);
    }
}
