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

            $shops = Shop::where('status', 1)
                ->where('is_recommended', 1)
                ->where('audit_status', 1)
                ->order('rating', 'desc')
                ->order('sales_count', 'desc')
                ->limit($limit)
                ->select();

            $list = [];
            foreach ($shops as $shop) {
                $list[] = [
                    'id' => $shop->id,
                    'shop_name' => $shop->shop_name,
                    'shop_logo' => $shop->shop_logo,
                    'shop_banner' => $shop->shop_banner,
                    'description' => $shop->description,
                    'location' => $shop->location,
                    'rating' => $shop->rating,
                    'product_count' => $shop->product_count,
                    'sales_count' => $shop->sales_count,
                    'open_time' => $shop->open_time
                ];
            }

            return Response::success([
                'list' => $list
            ]);
        } catch (\Exception $e) {
            return Response::error('获取推荐店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 获取店铺详情（包含分类和商品）
     */
    public function shopPage()
    {
        try {
            $shopId = (int)$this->request->param('shop_id');
            $categoryId = $this->request->param('category_id', ''); // 分类筛选
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            if (!$shopId) {
                return Response::validateError('店铺ID不能为空');
            }

            // 获取店铺信息
            $shop = Shop::with(['user'])->find($shopId);

            if (!$shop) {
                return Response::error('店铺不存在');
            }

            if ($shop->status !== 1) {
                return Response::error('店铺已关闭');
            }

            if ($shop->audit_status !== 1) {
                return Response::error('店铺审核未通过');
            }

            // 获取店铺的分类列表（只获取启用的二级分类）
            $shopCategories = \app\model\ShopCategory::with(['category'])
                ->where('shop_id', $shopId)
                ->where('status', 1)
                ->order('sort', 'asc')
                ->select();

            $categories = [];
            foreach ($shopCategories as $shopCategory) {
                if ($shopCategory->category && $shopCategory->category->parent_id > 0) {
                    // 只添加二级分类
                    // 统计该分类下的商品数量
                    $productCount = \app\model\Product::where('shop_id', $shopId)
                        ->where('category_id', $shopCategory->category_id)
                        ->where('status', 'on_sale')
                        ->count();

                    // 获取父分类信息
                    $parentCategory = \app\model\Category::find($shopCategory->category->parent_id);
                    $parentName = $parentCategory ? $parentCategory->name : '';

                    $categories[] = [
                        'id' => $shopCategory->category_id,
                        'parent_id' => $shopCategory->category->parent_id,
                        'parent_name' => $parentName,
                        'name' => $shopCategory->category->name,
                        'icon' => $shopCategory->category->icon,
                        'product_count' => $productCount
                    ];
                }
            }

            // 获取商品列表
            $query = \app\model\Product::where('shop_id', $shopId)
                ->where('status', 'on_sale');

            // 如果有分类筛选
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $query->order('created_at', 'desc');

            // 分页查询
            $products = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $productList = [];
            foreach ($products->items() as $product) {
                // 获取商品标签
                $tags = \app\model\ProductTag::where('product_id', $product->id)
                    ->column('tag_name');

                $productList[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->main_image,
                    'description' => $product->subtitle,
                    'price' => $product->price,
                    'unit' => $product->unit,
                    'sales' => $product->sales,
                    'stock' => $product->stock,
                    'rating' => $product->rating,
                    'tags' => $tags ?: []
                ];
            }

            // 店铺信息
            $shopInfo = [
                'id' => $shop->id,
                'shop_name' => $shop->shop_name,
                'shop_logo' => $shop->shop_logo,
                'shop_banner' => $shop->shop_banner,
                'description' => $shop->description,
                'location' => $shop->location,
                'rating' => $shop->rating,
                'product_count' => $shop->product_count,
                'sales_count' => $shop->sales_count,
                'open_time' => $shop->open_time
            ];

            // 农户信息
            $merchantInfo = null;
            if ($shop->user) {
                $merchantInfo = [
                    'id' => $shop->user->id,
                    'nickname' => $shop->user->nickname,
                    'avatar' => $shop->user->avatar,
                    'phone' => $shop->user->phone
                ];
            }

            return Response::success([
                'shop' => $shopInfo,
                'merchant' => $merchantInfo,
                'categories' => $categories,
                'products' => [
                    'list' => $productList,
                    'total' => $products->total(),
                    'page' => $page,
                    'page_size' => $pageSize
                ]
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取店铺页面失败：' . $e->getMessage());
            return Response::error('获取店铺页面失败：' . $e->getMessage());
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
     * 更新店铺设置
     */
    public function updateSettings()
    {
        try {
            $userId = $this->request->userId;

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 获取参数
            $shopName = $this->request->param('shop_name');
            $shopLogo = $this->request->param('shop_logo');
            $shopBanner = $this->request->param('shop_banner');
            $description = $this->request->param('description');
            $location = $this->request->param('location');
            $phone = $this->request->param('phone');

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

            if (empty($phone)) {
                return Response::validateError('联系电话不能为空');
            }

            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                return Response::validateError('请输入正确的手机号码');
            }

            // 开始事务
            \think\facade\Db::startTrans();
            try {
                // 更新店铺信息
                $shop->shop_name = $shopName;
                $shop->shop_logo = $shopLogo;
                $shop->shop_banner = $shopBanner;
                $shop->description = $description;
                $shop->location = $location;
                $shop->save();

                // 更新用户手机号
                $user = \app\model\User::find($userId);
                if ($user) {
                    $user->phone = $phone;
                    $user->save();
                }

                \think\facade\Db::commit();

                return Response::success([
                    'shop' => [
                        'id' => $shop->id,
                        'shop_name' => $shop->shop_name,
                        'shop_logo' => $shop->shop_logo,
                        'shop_banner' => $shop->shop_banner,
                        'description' => $shop->description,
                        'location' => $shop->location
                    ]
                ], '更新成功');
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('更新店铺设置失败：' . $e->getMessage());
            return Response::error('更新店铺设置失败：' . $e->getMessage());
        }
    }

    /**
     * 获取当前用户的店铺信息
     */
    public function myShop()
    {
        try {
            $userId = $this->request->userId;

            $shop = Shop::with(['user'])->where('user_id', $userId)->find();

            if (!$shop) {
                return Response::success([
                    'has_shop' => false,
                    'shop' => null,
                    'user' => null
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
                ],
                'user' => [
                    'phone' => $shop->user ? $shop->user->phone : ''
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取店铺信息失败：' . $e->getMessage());
        }
    }
}
