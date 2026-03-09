<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\SeasonalProduct;
use app\common\Response;

/**
 * 当季农产品控制器
 */
class SeasonalProductController extends BaseController
{
    /**
     * 获取当季农产品列表
     */
    public function list()
    {
        try {
            // 获取当前季节
            $currentSeason = SeasonalProduct::getCurrentSeason();

            // 获取指定季节（如果有传参）
            $season = $this->request->param('season', $currentSeason);

            // 验证季节参数
            $validSeasons = ['spring', 'summer', 'autumn', 'winter'];
            if (!in_array($season, $validSeasons)) {
                $season = $currentSeason;
            }

            // 查询当季农产品
            $products = SeasonalProduct::where('season', $season)
                ->where('status', 1)
                ->with(['category' => function ($query) {
                    $query->field('id,name');
                }])
                ->order('sort_order', 'asc')
                ->order('id', 'asc')
                ->select();

            return Response::success([
                'current_season' => $currentSeason,
                'selected_season' => $season,
                'products' => $products
            ]);
        } catch (\Exception $e) {
            return Response::error('获取当季农产品失败：' . $e->getMessage());
        }
    }

    /**
     * 获取所有季节的农产品
     */
    public function all()
    {
        try {
            $currentSeason = SeasonalProduct::getCurrentSeason();

            // 按季节分组查询
            $seasons = ['spring', 'summer', 'autumn', 'winter'];
            $seasonNames = [
                'spring' => '春季',
                'summer' => '夏季',
                'autumn' => '秋季',
                'winter' => '冬季'
            ];

            $result = [];
            foreach ($seasons as $season) {
                $products = SeasonalProduct::where('season', $season)
                    ->where('status', 1)
                    ->with(['category' => function ($query) {
                        $query->field('id,name');
                    }])
                    ->order('sort_order', 'asc')
                    ->order('id', 'asc')
                    ->select();

                $result[] = [
                    'season' => $season,
                    'season_name' => $seasonNames[$season],
                    'is_current' => $season === $currentSeason,
                    'products' => $products
                ];
            }

            return Response::success([
                'current_season' => $currentSeason,
                'seasons' => $result
            ]);
        } catch (\Exception $e) {
            return Response::error('获取农产品失败：' . $e->getMessage());
        }
    }
}
