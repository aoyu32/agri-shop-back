<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 分类模型
 */
class Category extends Model
{
    // 设置表名
    protected $name = 'categories';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 类型转换
    protected $type = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联商品
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * 关联父分类
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * 关联子分类
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->where('status', 1)->order('sort_order', 'asc');
    }

    /**
     * 获取所有一级分类
     */
    public static function getTopCategories()
    {
        return self::where('parent_id', 0)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select();
    }

    /**
     * 获取分类树（包含子分类）
     */
    public static function getCategoryTree()
    {
        return self::where('parent_id', 0)
            ->where('status', 1)
            ->with(['children'])
            ->order('sort_order', 'asc')
            ->select();
    }

    /**
     * 获取分类及其所有子分类的ID数组
     * @param int $categoryId 分类ID
     * @return array 包含该分类及其所有子分类的ID数组
     */
    public static function getCategoryWithChildren($categoryId)
    {
        $ids = [$categoryId];

        // 查询所有子分类
        $children = self::where('parent_id', $categoryId)
            ->where('status', 1)
            ->column('id');

        if (!empty($children)) {
            $ids = array_merge($ids, $children);
        }

        return $ids;
    }
}
