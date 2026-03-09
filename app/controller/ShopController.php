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

            if ($shop->audit_status !== 1) {
                return Response::error('店铺审核未通过');
            }

            return Response::success([
                'shop' => $shop
            ]);
        } catch (\Exception $e) {
            return Response::error('获取店铺详情失败：' . $e->getMessage());
        }
    }

    /**
     * 申请开通店铺
     */
    public function apply()
    {
        try {
            $userId = $this->request->userId;

            // 检查是否已有店铺
            $existShop = Shop::where('user_id', $userId)->find();
            if ($existShop) {
                // 如果店铺审核未通过，允许重新申请
                if ($existShop->audit_status === 2) {
                    // 更新店铺信息
                    $shopName = $this->request->param('shop_name');
                    $shopLogo = $this->request->param('shop_logo');
                    $shopBanner = $this->request->param('shop_banner');
                    $description = $this->request->param('description');
                    $location = $this->request->param('location');

                    // 验证必填字段
                    if (empty($shopName)) {
                        return Response::validateError('店铺名称不能为空');
                    }

                    if (mb_strlen($shopName) < 2 || mb_strlen($shopName) > 50) {
                        return Response::validateError('店铺名称长度为2-50个字符');
                    }

                    if (empty($shopLogo)) {
                        return Response::validateError('请上传店铺Logo');
                    }

                    if (empty($shopBanner)) {
                        return Response::validateError('请上传店铺横幅');
                    }

                    if (empty($description)) {
                        return Response::validateError('店铺简介不能为空');
                    }

                    if (empty($location)) {
                        return Response::validateError('店铺地址不能为空');
                    }

                    // 更新店铺信息
                    $existShop->shop_name = $shopName;
                    $existShop->shop_logo = $shopLogo;
                    $existShop->shop_banner = $shopBanner;
                    $existShop->description = $description;
                    $existShop->location = $location;
                    $existShop->audit_status = 0; // 重新提交审核
                    $existShop->audit_reason = null;
                    $existShop->audited_at = null;
                    $existShop->save();

                    return Response::success([
                        'shop' => $existShop
                    ], '店铺申请已重新提交，请等待管理员审核');
                } else {
                    return Response::error('您已经申请过店铺了');
                }
            }

            // 获取表单数据
            $shopName = $this->request->param('shop_name');
            $shopLogo = $this->request->param('shop_logo');
            $shopBanner = $this->request->param('shop_banner');
            $description = $this->request->param('description');
            $location = $this->request->param('location');

            // 验证必填字段
            if (empty($shopName)) {
                return Response::validateError('店铺名称不能为空');
            }

            if (mb_strlen($shopName) < 2 || mb_strlen($shopName) > 50) {
                return Response::validateError('店铺名称长度为2-50个字符');
            }

            if (empty($shopLogo)) {
                return Response::validateError('请上传店铺Logo');
            }

            if (empty($shopBanner)) {
                return Response::validateError('请上传店铺横幅');
            }

            if (empty($description)) {
                return Response::validateError('店铺简介不能为空');
            }

            if (empty($location)) {
                return Response::validateError('店铺地址不能为空');
            }

            // 创建店铺
            $shop = Shop::create([
                'user_id' => $userId,
                'shop_name' => $shopName,
                'shop_logo' => $shopLogo,
                'shop_banner' => $shopBanner,
                'description' => $description,
                'location' => $location,
                'rating' => 5.00,
                'product_count' => 0,
                'sales_count' => 0,
                'is_recommended' => 0,
                'audit_status' => 0, // 待审核
                'status' => 1,
                'open_time' => date('Y-m-d')
            ]);

            return Response::success([
                'shop' => $shop
            ], '店铺申请已提交，请等待管理员审核');
        } catch (\Exception $e) {
            return Response::error('申请店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 获取当前用户的店铺信息
     */
    public function myShop()
    {
        try {
            $userId = $this->request->userId;

            $shop = Shop::where('user_id', $userId)->find();

            if (!$shop) {
                return Response::success([
                    'has_shop' => false,
                    'shop' => null
                ]);
            }

            return Response::success([
                'has_shop' => true,
                'shop' => [
                    'id' => $shop->id,
                    'shop_name' => $shop->shop_name,
                    'shop_logo' => $shop->shop_logo,
                    'shop_banner' => $shop->shop_banner,
                    'description' => $shop->description,
                    'location' => $shop->location,
                    'rating' => $shop->rating,
                    'product_count' => $shop->product_count,
                    'sales_count' => $shop->sales_count,
                    'audit_status' => $shop->audit_status,
                    'audit_reason' => $shop->audit_reason,
                    'audited_at' => $shop->audited_at,
                    'status' => $shop->status,
                    'open_time' => $shop->open_time,
                    'created_at' => $shop->created_at
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取店铺信息失败：' . $e->getMessage());
        }
    }
}
