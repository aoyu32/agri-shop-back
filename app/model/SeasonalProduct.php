<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 当季农产品模型
 */
class SeasonalProduct extends Model
{
    protected $name = 'seasonal_products';
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * 获取当前季节
     */
    public static function getCurrentSeason(): string
    {
        $month = (int)date('n');

        if ($month >= 3 && $month <= 5) {
            return 'spring'; // 春季: 3-5月
        } elseif ($month >= 6 && $month <= 8) {
            return 'summer'; // 夏季: 6-8月
        } elseif ($month >= 9 && $month <= 11) {
            return 'autumn'; // 秋季: 9-11月
        } else {
            return 'winter'; // 冬季: 12-2月
        }
    }
}
