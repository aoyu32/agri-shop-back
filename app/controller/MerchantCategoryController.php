<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Shop;
use app\model\Category;
use app\model\ShopCategory;
use app\model\Product;
use app\common\Response;

/**
 * 农户分类管理控制器
 */
class MerchantCategoryController extends BaseController
{
    /**
     * 获取系统分类列表（供农户选择）
     */
    public function systemCategories()
    {
        try {
            // 获取所有启用的分类
            $categories = Category::where('status', 1)
                ->order('sort_order', 'asc')
                ->order('id', 'asc')
                ->select();

            $list = [];
            foreach ($categories as $category) {
                $list[] = [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'sort_order' => $category->sort_order
                ];
            }

            return Response::success([
                'list' => $list
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取店铺的分类列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺', [], 40001);
            }

            // 获取店铺的分类
            $shopCategories = ShopCategory::with(['category'])
                ->where('shop_id', $shop->id)
                ->order('sort', 'asc')
                ->select();

            $list = [];
            foreach ($shopCategories as $shopCategory) {
                if ($shopCategory->category) {
                    // 统计该分类下的商品数量
                    $productCount = Product::where('shop_id', $shop->id)
                        ->where('category_id', $shopCategory->category_id)
                        ->count();

                    // 获取父分类信息
                    $parentCategory = null;
                    $parentName = '';
                    if ($shopCategory->category->parent_id > 0) {
                        $parentCategory = Category::find($shopCategory->category->parent_id);
                        $parentName = $parentCategory ? $parentCategory->name : '';
                    }

                    $list[] = [
                        'id' => $shopCategory->id,
                        'category_id' => $shopCategory->category_id,
                        'parent_id' => $shopCategory->category->parent_id,
                        'parent_name' => $parentName,
                        'name' => $shopCategory->category->name,
                        'icon' => $shopCategory->category->icon,
                        'description' => $shopCategory->category->name . '分类',
                        'sort' => $shopCategory->sort,
                        'status' => $shopCategory->status ?? 1,
                        'productCount' => $productCount,
                        'createTime' => $shopCategory->created_at
                    ];
                }
            }

            return Response::success([
                'list' => $list
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取店铺分类失败：' . $e->getMessage());
            return Response::error('获取分类列表失败：' . $e->getMessage());
        }
    }

    /**
     * 添加分类到店铺
     */
    public function add()
    {
        try {
            $userId = $this->request->userId;
            $categoryId = $this->request->param('category_id');
            $sort = $this->request->param('sort', 0);

            if (!$categoryId) {
                return Response::validateError('请选择分类');
            }

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 检查分类是否存在
            $category = Category::where('id', $categoryId)
                ->where('status', 1)
                ->find();
            if (!$category) {
                return Response::error('分类不存在或已禁用');
            }

            // 检查是否已经添加过
            $exists = ShopCategory::where('shop_id', $shop->id)
                ->where('category_id', $categoryId)
                ->find();
            if ($exists) {
                return Response::error('该分类已添加，请勿重复添加');
            }

            // 创建店铺分类关联
            ShopCategory::create([
                'shop_id' => $shop->id,
                'category_id' => $categoryId,
                'sort' => $sort
            ]);

            return Response::success([], '添加成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('添加分类失败：' . $e->getMessage());
            return Response::error('添加分类失败：' . $e->getMessage());
        }
    }

    /**
     * 更新分类
     */
    public function update()
    {
        try {
            $userId = $this->request->userId;
            $id = $this->request->param('id');
            $categoryId = $this->request->param('category_id');
            $sort = $this->request->param('sort');

            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 获取店铺分类
            $shopCategory = ShopCategory::where('id', $id)
                ->where('shop_id', $shop->id)
                ->find();
            if (!$shopCategory) {
                return Response::error('分类不存在');
            }

            // 如果要更新分类
            if ($categoryId !== null && $categoryId != $shopCategory->category_id) {
                // 检查新分类是否存在
                $category = Category::where('id', $categoryId)
                    ->where('status', 1)
                    ->find();
                if (!$category) {
                    return Response::error('分类不存在或已禁用');
                }

                // 检查新分类是否已经添加过
                $exists = ShopCategory::where('shop_id', $shop->id)
                    ->where('category_id', $categoryId)
                    ->where('id', '<>', $id)
                    ->find();
                if ($exists) {
                    return Response::error('该分类已添加，请勿重复添加');
                }

                $shopCategory->category_id = $categoryId;
            }

            // 更新排序
            if ($sort !== null) {
                $shopCategory->sort = $sort;
            }

            $shopCategory->save();

            return Response::success([], '更新成功');
        } catch (\Exception $e) {
            return Response::error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 切换分类状态（启用/禁用）
     */
    public function toggleStatus()
    {
        try {
            $userId = $this->request->userId;
            $id = $this->request->param('id');

            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 获取店铺分类
            $shopCategory = ShopCategory::where('id', $id)
                ->where('shop_id', $shop->id)
                ->find();
            if (!$shopCategory) {
                return Response::error('分类不存在');
            }

            // 切换状态
            $shopCategory->status = $shopCategory->status == 1 ? 0 : 1;
            $shopCategory->save();

            $statusText = $shopCategory->status == 1 ? '启用' : '禁用';
            return Response::success([], $statusText . '成功');
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 删除店铺分类
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $id = $this->request->param('id');

            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 获取店铺分类
            $shopCategory = ShopCategory::where('id', $id)
                ->where('shop_id', $shop->id)
                ->find();
            if (!$shopCategory) {
                return Response::error('分类不存在');
            }

            // 检查该分类下是否有商品
            $productCount = Product::where('shop_id', $shop->id)
                ->where('category_id', $shopCategory->category_id)
                ->count();

            if ($productCount > 0) {
                return Response::error('该分类下还有商品，无法删除');
            }

            // 删除
            $shopCategory->delete();

            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }
}
