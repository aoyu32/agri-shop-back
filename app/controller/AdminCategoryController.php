<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Response;
use app\model\Category;
use app\model\Product;
use app\model\ShopCategory;

/**
 * 管理后台分类管理控制器
 */
class AdminCategoryController extends BaseController
{
    /**
     * 分类列表
     */
    public function list()
    {
        try {
            $keyword = trim((string) $this->request->param('keyword', ''));
            $status = $this->request->param('status', '');
            $page = (int) $this->request->param('page', 1);
            $pageSize = (int) $this->request->param('page_size', 10);

            $query = Category::order('parent_id', 'asc')
                ->order('sort_order', 'asc')
                ->order('id', 'asc');

            if ($keyword !== '') {
                $query->where('name', 'like', "%{$keyword}%");
            }

            if ($status !== '' && in_array((int) $status, [0, 1], true)) {
                $query->where('status', (int) $status);
            }

            $categories = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);
            $allCategories = Category::select();
            $nameMap = $allCategories->column('name', 'id');

            $list = [];
            foreach ($categories->items() as $category) {
                $list[] = [
                    'id' => $category->id,
                    'parent_id' => (int) $category->parent_id,
                    'parent_name' => $category->parent_id ? ($nameMap[$category->parent_id] ?? '') : '',
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'sort_order' => (int) $category->sort_order,
                    'status' => (int) $category->status,
                    'child_count' => Category::where('parent_id', $category->id)->count(),
                    'product_count' => Product::where('category_id', $category->id)->count(),
                    'shop_count' => ShopCategory::where('category_id', $category->id)->count(),
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ];
            }

            return Response::success([
                'list' => $list,
                'total' => $categories->total(),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类列表失败：' . $e->getMessage());
        }
    }

    /**
     * 分类详情
     */
    public function detail()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            $category = Category::find($id);
            if (!$category) {
                return Response::error('分类不存在');
            }

            return Response::success([
                'category' => [
                    'id' => $category->id,
                    'parent_id' => (int) $category->parent_id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'sort_order' => (int) $category->sort_order,
                    'status' => (int) $category->status,
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类详情失败：' . $e->getMessage());
        }
    }

    /**
     * 新增分类
     */
    public function create()
    {
        try {
            $parentId = (int) $this->request->param('parent_id', 0);
            $name = trim((string) $this->request->param('name'));
            $icon = trim((string) $this->request->param('icon', ''));
            $sortOrder = (int) $this->request->param('sort_order', 0);
            $status = (int) $this->request->param('status', 1);

            if ($name === '' || mb_strlen($name) > 50) {
                return Response::validateError('分类名称不能为空且不能超过50个字符');
            }
            if ($parentId > 0 && !Category::find($parentId)) {
                return Response::validateError('父级分类不存在');
            }

            $exists = Category::where('parent_id', $parentId)->where('name', $name)->find();
            if ($exists) {
                return Response::validateError('同级分类名称已存在');
            }

            $category = Category::create([
                'parent_id' => $parentId,
                'name' => $name,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'status' => in_array($status, [0, 1], true) ? $status : 1,
            ]);

            return Response::success([
                'category' => ['id' => $category->id],
            ], '新增分类成功');
        } catch (\Exception $e) {
            return Response::error('新增分类失败：' . $e->getMessage());
        }
    }

    /**
     * 更新分类
     */
    public function update()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            $category = Category::find($id);
            if (!$category) {
                return Response::error('分类不存在');
            }

            $parentId = (int) $this->request->param('parent_id', $category->parent_id);
            $name = trim((string) $this->request->param('name', $category->name));
            $icon = trim((string) $this->request->param('icon', $category->icon));
            $sortOrder = (int) $this->request->param('sort_order', $category->sort_order);
            $status = (int) $this->request->param('status', $category->status);

            if ($name === '' || mb_strlen($name) > 50) {
                return Response::validateError('分类名称不能为空且不能超过50个字符');
            }
            if ($parentId === $id) {
                return Response::validateError('父级分类不能选择自己');
            }
            if ($parentId > 0 && !Category::find($parentId)) {
                return Response::validateError('父级分类不存在');
            }

            $exists = Category::where('parent_id', $parentId)
                ->where('name', $name)
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                return Response::validateError('同级分类名称已存在');
            }

            $category->parent_id = $parentId;
            $category->name = $name;
            $category->icon = $icon;
            $category->sort_order = $sortOrder;
            $category->status = in_array($status, [0, 1], true) ? $status : 1;
            $category->save();

            return Response::success([], '更新分类成功');
        } catch (\Exception $e) {
            return Response::error('更新分类失败：' . $e->getMessage());
        }
    }

    /**
     * 删除分类
     */
    public function delete()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            $category = Category::find($id);
            if (!$category) {
                return Response::error('分类不存在');
            }

            if (Category::where('parent_id', $id)->count() > 0) {
                return Response::error('该分类下仍有子分类，无法删除');
            }
            if (Product::where('category_id', $id)->count() > 0) {
                return Response::error('该分类下仍有关联商品，无法删除');
            }
            if (ShopCategory::where('category_id', $id)->count() > 0) {
                return Response::error('该分类仍被店铺使用，无法删除');
            }

            $category->delete();

            return Response::success([], '删除分类成功');
        } catch (\Exception $e) {
            return Response::error('删除分类失败：' . $e->getMessage());
        }
    }
}
