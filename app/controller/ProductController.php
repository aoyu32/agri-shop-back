<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Product;
use app\common\Response;

/**
 * 商品控制器
 */
class ProductController extends BaseController
{
    /**
     * 获取热销商品列表
     */
    public function hotList()
    {
        try {
            $limit = (int)$this->request->param('limit', 10);

            $products = Product::getHotProducts($limit);

            // 加载关联数据
            $products->load(['shop', 'category', 'tags']);

            return Response::success([
                'list' => $products
            ]);
        } catch (\Exception $e) {
            return Response::error('获取热销商品失败：' . $e->getMessage());
        }
    }

    /**
     * 获取促销商品列表
     */
    public function promotionList()
    {
        try {
            $limit = (int)$this->request->param('limit', 10);

            $products = Product::getPromotionProducts($limit);

            // 加载关联数据
            $products->load(['shop', 'category', 'tags']);

            return Response::success([
                'list' => $products
            ]);
        } catch (\Exception $e) {
            return Response::error('获取促销商品失败：' . $e->getMessage());
        }
    }

    /**
     * 根据分类获取商品列表
     */
    public function listByCategory()
    {
        try {
            $categoryId = (int)$this->request->param('category_id');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            if (!$categoryId) {
                return Response::validateError('分类ID不能为空');
            }

            $products = Product::getProductsByCategory($categoryId, $page, $pageSize);

            // 加载关联数据
            $products->load(['shop', 'category', 'tags']);

            return Response::success([
                'list' => $products->items(),
                'total' => $products->total(),
                'page' => $products->currentPage(),
                'page_size' => $products->listRows(),
                'last_page' => $products->lastPage(),
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商品列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取商品详情
     */
    public function detail()
    {
        try {
            $id = (int)$this->request->param('id');

            if (!$id) {
                return Response::validateError('商品ID不能为空');
            }

            $product = Product::with(['shop', 'category', 'images', 'specs', 'tags'])
                ->find($id);

            if (!$product) {
                return Response::error('商品不存在');
            }

            if ($product->status !== 'on_sale') {
                return Response::error('商品已下架');
            }

            return Response::success([
                'product' => $product
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商品详情失败：' . $e->getMessage());
        }
    }

    /**
     * 搜索商品
     */
    public function search()
    {
        try {
            $keyword = $this->request->param('keyword', '');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $sortBy = $this->request->param('sort_by', 'sales');

            $query = Product::where('status', 'on_sale');

            // 关键词搜索
            if ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%');
            }

            // 排序
            switch ($sortBy) {
                case 'price_asc':
                    $query->order('price', 'asc');
                    break;
                case 'price_desc':
                    $query->order('price', 'desc');
                    break;
                case 'sales':
                default:
                    $query->order('sales', 'desc');
                    break;
            }

            $products = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);

            // 加载关联数据
            $products->load(['shop', 'category', 'tags']);

            return Response::success([
                'list' => $products->items(),
                'total' => $products->total(),
                'page' => $products->currentPage(),
                'page_size' => $products->listRows(),
                'last_page' => $products->lastPage(),
            ]);
        } catch (\Exception $e) {
            return Response::error('搜索商品失败：' . $e->getMessage());
        }
    }

    /**
     * 获取所有商品（分页）
     */
    public function list()
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $products = Product::where('status', 'on_sale')
                ->order('sales', 'desc')
                ->paginate([
                    'list_rows' => $pageSize,
                    'page' => $page,
                ]);

            // 加载关联数据
            $products->load(['shop', 'category', 'tags']);

            return Response::success([
                'list' => $products->items(),
                'total' => $products->total(),
                'page' => $products->currentPage(),
                'page_size' => $products->listRows(),
                'last_page' => $products->lastPage(),
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商品列表失败：' . $e->getMessage());
        }
    }
}
