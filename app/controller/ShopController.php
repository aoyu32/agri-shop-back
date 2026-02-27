<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Shop;
use app\common\Response;

/**
 * 店铺控制器
 */
class ShopController extends BaseController
{
    /**
     * 获取推荐店铺列表
     */
    public function recommendedList()
    {
        try {
            $limit = (int)$this->request->param('limit', 10);

            $shops = Shop::getRecommendedShops($limit);

            // 加载关联用户数据
            $shops->load(['user']);

            return Response::success([
                'list' => $shops
            ]);
        } catch (\Exception $e) {
            return Response::error('获取推荐店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 获取店铺详情
     */
    public function detail()
    {
        try {
            $id = (int)$this->request->param('id');

            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }

            $shop = Shop::with(['user'])->find($id);

            if (!$shop) {
                return Response::error('店铺不存在');
            }

            if ($shop->status !== 1) {
                return Response::error('店铺已关闭');
            }

            return Response::success([
                'shop' => $shop
            ]);
        } catch (\Exception $e) {
            return Response::error('获取店铺详情失败：' . $e->getMessage());
        }
    }
}
