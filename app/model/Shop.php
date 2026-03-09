<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 店铺模型
 */
class Shop extends Model
{
    protected $name = 'shops';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取推荐店铺
     * @param int $limit
     * @return \think\Collection
     */
    public static function getRecommendedShops(int $limit = 10)
    {
        return self::where('status', 1)
            ->where('is_recommended', 1)
            ->where('audit_status', 1) // 只显示审核通过的店铺
            ->order('rating', 'desc')
            ->order('sales_count', 'desc')
            ->limit($limit)
            ->select();
    }
}
