<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Cart;
use app\model\Product;
use app\model\ProductSpec;
use app\common\Response;

/**
 * 购物车控制器
 */
class CartController extends BaseController
{
    /**
     * 获取购物车列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;

            $cartItems = Cart::where('user_id', $userId)
                ->with(['product.shop', 'product.tags', 'spec'])
                ->order('created_at', 'desc')
                ->select();

            // 格式化数据
            $list = [];
            foreach ($cartItems as $item) {
                if (!$item->product) {
                    continue; // 商品已删除，跳过
                }

                $product = $item->product;
                $spec = $item->spec;

                // 计算价格
                $price = (float)$product->price;
                if ($spec) {
                    $price += (float)$spec->price_diff;
                }

                $list[] = [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->main_image,
                    'product_unit' => $product->unit,
                    'spec_id' => $spec ? $spec->id : null,
                    'spec_label' => $spec ? $spec->spec_label : '默认',
                    'price' => $price,
                    'quantity' => $item->quantity,
                    'stock' => $spec ? $spec->stock : $product->stock,
                    'checked' => $item->checked,
                    'shop_name' => $product->shop ? $product->shop->shop_name : '未知店铺',
                    'tags' => $product->tags ? $product->tags->column('tag_name') : []
                ];
            }

            return Response::success([
                'list' => $list
            ]);
        } catch (\Exception $e) {
            return Response::error('获取购物车失败：' . $e->getMessage());
        }
    }

    /**
     * 添加到购物车
     */
    public function add()
    {
        try {
            $userId = $this->request->userId;
            $productId = (int)$this->request->param('product_id');
            $specId = $this->request->param('spec_id', null);
            $quantity = (int)$this->request->param('quantity', 1);

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            if ($quantity < 1) {
                return Response::validateError('数量必须大于0');
            }

            // 检查商品是否存在
            $product = Product::find($productId);
            if (!$product || $product->status !== 'on_sale') {
                return Response::error('商品不存在或已下架');
            }

            // 检查规格
            if ($specId) {
                $spec = ProductSpec::find($specId);
                if (!$spec || $spec->product_id != $productId) {
                    return Response::error('规格不存在');
                }
                if ($spec->stock < $quantity) {
                    return Response::error('库存不足');
                }
            } else {
                if ($product->stock < $quantity) {
                    return Response::error('库存不足');
                }
            }

            // 查找是否已存在
            $cart = Cart::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('spec_id', $specId ?: null)
                ->find();

            if ($cart) {
                // 更新数量
                $cart->quantity += $quantity;
                $cart->save();
            } else {
                // 新增
                Cart::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'spec_id' => $specId ?: null,
                    'quantity' => $quantity,
                    'checked' => 1
                ]);
            }

            return Response::success([], '添加成功');
        } catch (\Exception $e) {
            return Response::error('添加失败：' . $e->getMessage());
        }
    }

    /**
     * 更新购物车商品数量
     */
    public function updateQuantity()
    {
        try {
            $userId = $this->request->userId;
            $cartId = (int)$this->request->param('cart_id');
            $quantity = (int)$this->request->param('quantity');

            if (!$cartId) {
                return Response::validateError('购物车ID不能为空');
            }

            if ($quantity < 1) {
                return Response::validateError('数量必须大于0');
            }

            $cart = Cart::where('id', $cartId)
                ->where('user_id', $userId)
                ->find();

            if (!$cart) {
                return Response::error('购物车项不存在');
            }

            // 检查库存
            $product = Product::find($cart->product_id);
            if ($cart->spec_id) {
                $spec = ProductSpec::find($cart->spec_id);
                if ($spec && $spec->stock < $quantity) {
                    return Response::error('库存不足');
                }
            } else {
                if ($product && $product->stock < $quantity) {
                    return Response::error('库存不足');
                }
            }

            $cart->quantity = $quantity;
            $cart->save();

            return Response::success([], '更新成功');
        } catch (\Exception $e) {
            return Response::error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 切换选中状态
     */
    public function toggleCheck()
    {
        try {
            $userId = $this->request->userId;
            $cartId = (int)$this->request->param('cart_id');
            $checked = (int)$this->request->param('checked', 1);

            if (!$cartId) {
                return Response::validateError('购物车ID不能为空');
            }

            $cart = Cart::where('id', $cartId)
                ->where('user_id', $userId)
                ->find();

            if (!$cart) {
                return Response::error('购物车项不存在');
            }

            $cart->checked = $checked ? 1 : 0;
            $cart->save();

            return Response::success([], '操作成功');
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 全选/取消全选
     */
    public function checkAll()
    {
        try {
            $userId = $this->request->userId;
            $checked = (int)$this->request->param('checked', 1);

            Cart::where('user_id', $userId)
                ->update(['checked' => $checked ? 1 : 0]);

            return Response::success([], '操作成功');
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 删除购物车商品
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $cartIds = $this->request->param('cart_ids');

            if (empty($cartIds)) {
                return Response::validateError('购物车ID不能为空');
            }

            if (is_string($cartIds)) {
                $cartIds = explode(',', $cartIds);
            }

            Cart::where('user_id', $userId)
                ->whereIn('id', $cartIds)
                ->delete();

            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 清空购物车
     */
    public function clear()
    {
        try {
            $userId = $this->request->userId;

            Cart::where('user_id', $userId)->delete();

            return Response::success([], '清空成功');
        } catch (\Exception $e) {
            return Response::error('清空失败：' . $e->getMessage());
        }
    }

    /**
     * 获取购物车统计信息
     */
    public function count()
    {
        try {
            $userId = $this->request->userId;

            $count = Cart::where('user_id', $userId)->count();

            return Response::success([
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return Response::error('获取统计失败：' . $e->getMessage());
        }
    }
}
