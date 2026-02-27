<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Category;
use app\common\Response;

/**
 * 分类控制器
 */
class CategoryController extends BaseController
{
    /**
     * 获取所有一级分类
     */
    public function list()
    {
        try {
            $categories = Category::getTopCategories();

            return Response::success([
                'list' => $categories
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取分类树（包含子分类）
     */
    public function tree()
    {
        try {
            $categories = Category::getCategoryTree();

            return Response::success([
                'list' => $categories
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类树失败：' . $e->getMessage());
        }
    }

    /**
     * 获取分类详情
     */
    public function detail()
    {
        try {
            $id = $this->request->param('id');

            if (!$id) {
                return Response::validateError('分类ID不能为空');
            }

            $category = Category::with(['children'])->find($id);

            if (!$category) {
                return Response::error('分类不存在');
            }

            return Response::success([
                'category' => $category
            ]);
        } catch (\Exception $e) {
            return Response::error('获取分类详情失败：' . $e->getMessage());
        }
    }
}
