<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Shop;
use app\model\Product;
use app\model\ProductImage;
use app\model\ProductSpec;
use app\model\ProductTag;
use app\common\Response;

/**
 * 农户商品管理控制器
 */
class MerchantProductController extends BaseController
{
    /**
     * 获取商品列表
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

            // 获取筛选参数
            $keyword = $this->request->param('keyword', '');
            $status = $this->request->param('status', '');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            // 构建查询
            $query = Product::where('shop_id', $shop->id);

            // 关键词搜索
            if ($keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            }

            // 状态筛选
            if ($status) {
                $query->where('status', $status);
            }

            $query->order('created_at', 'desc');

            // 分页查询
            $products = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($products->items() as $product) {
                $list[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'subtitle' => $product->subtitle,
                    'category_id' => $product->category_id,
                    'image' => $product->main_image,
                    'price' => $product->price,
                    'original_price' => $product->original_price,
                    'stock' => $product->stock,
                    'sales' => $product->sales,
                    'status' => $product->status,
                    'created_at' => $product->created_at
                ];
            }

            return Response::success([
                'list' => $list,
                'total' => $products->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取商品列表失败：' . $e->getMessage());
            return Response::error('获取商品列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取商品详情
     */
    public function detail()
    {
        try {
            $userId = $this->request->userId;
            $productId = $this->request->param('id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $product = Product::where('id', $productId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$product) {
                return Response::error('商品不存在');
            }

            // 获取商品图片
            $images = ProductImage::where('product_id', $productId)
                ->order('sort_order', 'asc')
                ->column('image_url');

            // 获取商品规格
            $specs = ProductSpec::where('product_id', $productId)
                ->select();

            $specList = [];
            foreach ($specs as $spec) {
                $specList[] = [
                    'id' => $spec->id,
                    'label' => $spec->spec_label,
                    'price' => $spec->price_diff,
                    'stock' => $spec->stock
                ];
            }

            // 获取商品标签
            $tags = ProductTag::where('product_id', $productId)
                ->column('tag_name');

            return Response::success([
                'id' => $product->id,
                'name' => $product->name,
                'subtitle' => $product->subtitle,
                'category_id' => $product->category_id,
                'images' => $images ?: [$product->main_image],
                'price' => $product->price,
                'original_price' => $product->original_price,
                'unit' => $product->unit,
                'stock' => $product->stock,
                'origin' => $product->origin,
                'detail' => $product->detail,
                'status' => $product->status,
                'specs' => $specList,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商品详情失败：' . $e->getMessage());
        }
    }

    /**
     * 添加商品
     */
    public function add()
    {
        try {
            $userId = $this->request->userId;

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 获取参数
            $data = $this->request->param();

            // 验证必填字段
            if (empty($data['name'])) {
                return Response::validateError('商品名称不能为空');
            }
            if (empty($data['category_id'])) {
                return Response::validateError('请选择商品分类');
            }
            if (!isset($data['price']) || $data['price'] <= 0) {
                return Response::validateError('请输入正确的商品价格');
            }
            if (empty($data['images']) || !is_array($data['images'])) {
                return Response::validateError('请上传商品图片');
            }

            // 开始事务
            Product::startTrans();
            try {
                // 创建商品
                $product = Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $data['category_id'],
                    'name' => $data['name'],
                    'subtitle' => $data['subtitle'] ?? '',
                    'main_image' => $data['images'][0],
                    'price' => $data['price'],
                    'original_price' => $data['original_price'] ?? $data['price'],
                    'unit' => $data['unit'] ?? '',
                    'stock' => $data['stock'] ?? 0,
                    'origin' => $data['origin'] ?? '',
                    'detail' => $data['detail'] ?? '',
                    'status' => 'on_sale'
                ]);

                // 保存商品图片
                foreach ($data['images'] as $index => $image) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $image,
                        'sort_order' => $index
                    ]);
                }

                // 保存商品规格
                if (!empty($data['specs']) && is_array($data['specs'])) {
                    foreach ($data['specs'] as $spec) {
                        if (!empty($spec['label'])) {
                            ProductSpec::create([
                                'product_id' => $product->id,
                                'spec_label' => $spec['label'],
                                'price_diff' => $spec['price'] ?? 0,
                                'stock' => $spec['stock'] ?? 0
                            ]);
                        }
                    }
                }

                // 保存商品标签
                if (!empty($data['tags']) && is_array($data['tags'])) {
                    foreach ($data['tags'] as $tag) {
                        if (!empty($tag)) {
                            ProductTag::create([
                                'product_id' => $product->id,
                                'tag_name' => $tag
                            ]);
                        }
                    }
                }

                // 更新店铺商品数量
                Shop::where('id', $shop->id)->inc('product_count')->update();

                Product::commit();
                return Response::success(['id' => $product->id], '添加成功');
            } catch (\Exception $e) {
                Product::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('添加商品失败：' . $e->getMessage());
            return Response::error('添加商品失败：' . $e->getMessage());
        }
    }

    /**
     * 更新商品
     */
    public function update()
    {
        try {
            $userId = $this->request->userId;
            $productId = $this->request->param('id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $product = Product::where('id', $productId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$product) {
                return Response::error('商品不存在');
            }

            // 获取参数
            $data = $this->request->param();

            // 开始事务
            Product::startTrans();
            try {
                // 更新商品基本信息
                $product->name = $data['name'] ?? $product->name;
                $product->subtitle = $data['subtitle'] ?? $product->subtitle;
                $product->category_id = $data['category_id'] ?? $product->category_id;
                $product->price = $data['price'] ?? $product->price;
                $product->original_price = $data['original_price'] ?? $product->original_price;
                $product->unit = $data['unit'] ?? $product->unit;
                $product->stock = $data['stock'] ?? $product->stock;
                $product->origin = $data['origin'] ?? $product->origin;
                $product->detail = $data['detail'] ?? $product->detail;

                if (!empty($data['images']) && is_array($data['images'])) {
                    $product->main_image = $data['images'][0];

                    // 删除旧图片
                    ProductImage::where('product_id', $productId)->delete();

                    // 保存新图片
                    foreach ($data['images'] as $index => $image) {
                        ProductImage::create([
                            'product_id' => $productId,
                            'image_url' => $image,
                            'sort_order' => $index
                        ]);
                    }
                }

                $product->save();

                // 更新规格
                if (isset($data['specs'])) {
                    ProductSpec::where('product_id', $productId)->delete();
                    if (is_array($data['specs'])) {
                        foreach ($data['specs'] as $spec) {
                            if (!empty($spec['label'])) {
                                ProductSpec::create([
                                    'product_id' => $productId,
                                    'spec_label' => $spec['label'],
                                    'price_diff' => $spec['price'] ?? 0,
                                    'stock' => $spec['stock'] ?? 0
                                ]);
                            }
                        }
                    }
                }

                // 更新标签
                if (isset($data['tags'])) {
                    ProductTag::where('product_id', $productId)->delete();
                    if (is_array($data['tags'])) {
                        foreach ($data['tags'] as $tag) {
                            if (!empty($tag)) {
                                ProductTag::create([
                                    'product_id' => $productId,
                                    'tag_name' => $tag
                                ]);
                            }
                        }
                    }
                }

                Product::commit();
                return Response::success([], '更新成功');
            } catch (\Exception $e) {
                Product::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('更新商品失败：' . $e->getMessage());
            return Response::error('更新商品失败：' . $e->getMessage());
        }
    }

    /**
     * 切换商品状态（上架/下架）
     */
    public function toggleStatus()
    {
        try {
            $userId = $this->request->userId;
            $productId = $this->request->param('id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $product = Product::where('id', $productId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$product) {
                return Response::error('商品不存在');
            }

            // 切换状态
            $product->status = $product->status === 'on_sale' ? 'off_sale' : 'on_sale';
            $product->save();

            $statusText = $product->status === 'on_sale' ? '上架' : '下架';
            return Response::success([], $statusText . '成功');
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 删除商品
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $productId = $this->request->param('id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $product = Product::where('id', $productId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$product) {
                return Response::error('商品不存在');
            }

            // 开始事务
            Product::startTrans();
            try {
                // 删除商品图片
                ProductImage::where('product_id', $productId)->delete();

                // 删除商品规格
                ProductSpec::where('product_id', $productId)->delete();

                // 删除商品标签
                ProductTag::where('product_id', $productId)->delete();

                // 删除商品
                $product->delete();

                // 更新店铺商品数量
                Shop::where('id', $shop->id)->dec('product_count')->update();

                Product::commit();
                return Response::success([], '删除成功');
            } catch (\Exception $e) {
                Product::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }
}
