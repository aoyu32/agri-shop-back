<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Response;
use app\model\Order;
use app\model\Product;
use app\model\Shop;
use app\model\ShopCategory;
use app\model\User;
use think\facade\Db;

/**
 * 管理后台商户管理控制器
 */
class AdminMerchantController extends BaseController
{
    /**
     * 商户店铺列表
     */
    public function list()
    {
        try {
            $keyword = trim((string) $this->request->param('keyword', ''));
            $auditStatus = $this->request->param('audit_status', '');
            $status = $this->request->param('status', '');
            $page = (int) $this->request->param('page', 1);
            $pageSize = (int) $this->request->param('page_size', 10);

            $query = Db::name('shops')
                ->alias('s')
                ->leftJoin('users u', 'u.id = s.user_id')
                ->field([
                    's.id',
                    's.user_id',
                    's.shop_name',
                    's.shop_logo',
                    's.shop_banner',
                    's.description',
                    's.location',
                    's.rating',
                    's.product_count',
                    's.sales_count',
                    's.is_recommended',
                    's.audit_status',
                    's.audit_reason',
                    's.audited_at',
                    's.status',
                    's.open_time',
                    's.created_at',
                    's.updated_at',
                    'u.username',
                    'u.nickname',
                    'u.phone',
                    'u.avatar as user_avatar',
                ]);

            if ($keyword !== '') {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->whereOr('s.shop_name', 'like', "%{$keyword}%")
                        ->whereOr('s.location', 'like', "%{$keyword}%")
                        ->whereOr('u.username', 'like', "%{$keyword}%")
                        ->whereOr('u.nickname', 'like', "%{$keyword}%")
                        ->whereOr('u.phone', 'like', "%{$keyword}%");
                });
            }

            if ($auditStatus !== '' && in_array((int) $auditStatus, [0, 1, 2], true)) {
                $query->where('s.audit_status', (int) $auditStatus);
            }

            if ($status !== '' && in_array((int) $status, [0, 1], true)) {
                $query->where('s.status', (int) $status);
            }

            $shops = $query->order('s.id', 'desc')->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);

            $list = [];
            foreach ($shops->items() as $shop) {
                $list[] = $this->formatShopRow($shop);
            }

            return Response::success([
                'list' => $list,
                'total' => $shops->total(),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商户列表失败：' . $e->getMessage());
        }
    }

    /**
     * 商户详情
     */
    public function detail()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }

            $shop = Shop::with(['user'])->find($id);
            if (!$shop) {
                return Response::error('店铺不存在');
            }

            return Response::success([
                'shop' => [
                    'id' => $shop->id,
                    'user_id' => $shop->user_id,
                    'shop_name' => $shop->shop_name,
                    'shop_logo' => $shop->shop_logo,
                    'shop_banner' => $shop->shop_banner,
                    'description' => $shop->description,
                    'location' => $shop->location,
                    'rating' => (float) $shop->rating,
                    'product_count' => (int) $shop->product_count,
                    'sales_count' => (int) $shop->sales_count,
                    'is_recommended' => (int) $shop->is_recommended,
                    'audit_status' => (int) $shop->audit_status,
                    'audit_reason' => $shop->audit_reason,
                    'audited_at' => $shop->audited_at,
                    'status' => (int) $shop->status,
                    'open_time' => $shop->open_time,
                    'created_at' => $shop->created_at,
                    'merchant' => $shop->user ? [
                        'id' => $shop->user->id,
                        'username' => $shop->user->username,
                        'nickname' => $shop->user->nickname,
                        'phone' => $shop->user->phone,
                        'avatar' => $shop->user->avatar,
                    ] : null,
                    'category_count' => ShopCategory::where('shop_id', $shop->id)->count(),
                    'order_count' => Order::where('shop_id', $shop->id)->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error('获取商户详情失败：' . $e->getMessage());
        }
    }

    /**
     * 可绑定商户用户列表
     */
    public function availableUsers()
    {
        try {
            $shopUserIds = Shop::column('user_id');

            $query = User::where('role', 'merchant')->where('status', 1);
            if (!empty($shopUserIds)) {
                $query->whereNotIn('id', $shopUserIds);
            }

            $users = $query->order('id', 'desc')->select();
            $list = [];
            foreach ($users as $user) {
                $list[] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                ];
            }

            return Response::success([
                'list' => $list,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取可选商户失败：' . $e->getMessage());
        }
    }

    /**
     * 新增店铺
     */
    public function create()
    {
        try {
            $userId = (int) $this->request->param('user_id');
            $shopName = trim((string) $this->request->param('shop_name'));
            $shopLogo = trim((string) $this->request->param('shop_logo'));
            $shopBanner = trim((string) $this->request->param('shop_banner'));
            $description = trim((string) $this->request->param('description', ''));
            $location = trim((string) $this->request->param('location', ''));
            $isRecommended = (int) $this->request->param('is_recommended', 0);
            $status = (int) $this->request->param('status', 1);
            $auditStatus = (int) $this->request->param('audit_status', 1);

            if (!$userId) {
                return Response::validateError('请选择商户账号');
            }
            if ($shopName === '' || mb_strlen($shopName) < 2 || mb_strlen($shopName) > 50) {
                return Response::validateError('店铺名称长度需为2-50个字符');
            }
            if ($shopLogo === '') {
                return Response::validateError('请上传店铺Logo');
            }
            if ($shopBanner === '') {
                return Response::validateError('请上传店铺横幅');
            }

            $merchant = User::where('id', $userId)->where('role', 'merchant')->find();
            if (!$merchant) {
                return Response::error('商户账号不存在');
            }
            if (Shop::where('user_id', $userId)->find()) {
                return Response::error('该商户已开通店铺');
            }

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
                'is_recommended' => $isRecommended ? 1 : 0,
                'audit_status' => in_array($auditStatus, [0, 1, 2], true) ? $auditStatus : 1,
                'status' => $status === 0 ? 0 : 1,
                'open_time' => date('Y-m-d'),
                'audited_at' => $auditStatus === 1 ? date('Y-m-d H:i:s') : null,
            ]);

            return Response::success([
                'shop' => ['id' => $shop->id],
            ], '新增店铺成功');
        } catch (\Exception $e) {
            return Response::error('新增店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 更新店铺
     */
    public function update()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }

            $shop = Shop::find($id);
            if (!$shop) {
                return Response::error('店铺不存在');
            }

            $shopName = trim((string) $this->request->param('shop_name', $shop->shop_name));
            $shopLogo = trim((string) $this->request->param('shop_logo', $shop->shop_logo));
            $shopBanner = trim((string) $this->request->param('shop_banner', $shop->shop_banner));
            $description = trim((string) $this->request->param('description', $shop->description));
            $location = trim((string) $this->request->param('location', $shop->location));
            $isRecommended = (int) $this->request->param('is_recommended', $shop->is_recommended);
            $status = (int) $this->request->param('status', $shop->status);
            $phone = trim((string) $this->request->param('phone', ''));

            if ($shopName === '' || mb_strlen($shopName) < 2 || mb_strlen($shopName) > 50) {
                return Response::validateError('店铺名称长度需为2-50个字符');
            }
            if ($shopLogo === '') {
                return Response::validateError('请上传店铺Logo');
            }
            if ($shopBanner === '') {
                return Response::validateError('请上传店铺横幅');
            }

            Db::startTrans();
            try {
                $shop->shop_name = $shopName;
                $shop->shop_logo = $shopLogo;
                $shop->shop_banner = $shopBanner;
                $shop->description = $description;
                $shop->location = $location;
                $shop->is_recommended = $isRecommended ? 1 : 0;
                $shop->status = $status === 0 ? 0 : 1;
                $shop->save();

                if ($phone !== '') {
                    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                        throw new \Exception('请输入正确的手机号');
                    }

                    $user = User::find($shop->user_id);
                    if ($user) {
                        $exists = User::where('phone', $phone)->where('id', '<>', $user->id)->find();
                        if ($exists) {
                            throw new \Exception('手机号已被其他用户使用');
                        }
                        $user->phone = $phone;
                        $user->save();
                    }
                }

                Db::commit();
                return Response::success([], '更新店铺成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('更新店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 审核店铺
     */
    public function review()
    {
        try {
            $id = (int) $this->request->param('id');
            $auditStatus = (int) $this->request->param('audit_status');
            $auditReason = trim((string) $this->request->param('audit_reason', ''));

            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }
            if (!in_array($auditStatus, [1, 2], true)) {
                return Response::validateError('审核状态不正确');
            }
            if ($auditStatus === 2 && $auditReason === '') {
                return Response::validateError('请填写拒绝原因');
            }

            $shop = Shop::find($id);
            if (!$shop) {
                return Response::error('店铺不存在');
            }

            $shop->audit_status = $auditStatus;
            $shop->audit_reason = $auditStatus === 2 ? $auditReason : null;
            $shop->audited_at = date('Y-m-d H:i:s');
            $shop->save();

            return Response::success([], $auditStatus === 1 ? '审核通过' : '审核驳回');
        } catch (\Exception $e) {
            return Response::error('店铺审核失败：' . $e->getMessage());
        }
    }

    /**
     * 店铺状态切换
     */
    public function toggleStatus()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }

            $shop = Shop::find($id);
            if (!$shop) {
                return Response::error('店铺不存在');
            }

            $shop->status = (int) $shop->status === 1 ? 0 : 1;
            $shop->save();

            return Response::success([], (int) $shop->status === 1 ? '店铺已开启' : '店铺已关闭');
        } catch (\Exception $e) {
            return Response::error('更新店铺状态失败：' . $e->getMessage());
        }
    }

    /**
     * 删除店铺
     */
    public function delete()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('店铺ID不能为空');
            }

            $shop = Shop::find($id);
            if (!$shop) {
                return Response::error('店铺不存在');
            }

            if (Product::where('shop_id', $id)->count() > 0 || Order::where('shop_id', $id)->count() > 0) {
                return Response::error('该店铺已存在商品或订单数据，无法删除');
            }

            Db::startTrans();
            try {
                ShopCategory::where('shop_id', $id)->delete();
                $shop->delete();
                Db::commit();

                return Response::success([], '删除店铺成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('删除店铺失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化店铺数据
     */
    private function formatShopRow(array $shop): array
    {
        return [
            'id' => (int) $shop['id'],
            'user_id' => (int) $shop['user_id'],
            'shop_name' => $shop['shop_name'],
            'shop_logo' => $shop['shop_logo'],
            'shop_banner' => $shop['shop_banner'],
            'description' => $shop['description'],
            'location' => $shop['location'],
            'rating' => (float) $shop['rating'],
            'product_count' => (int) $shop['product_count'],
            'sales_count' => (int) $shop['sales_count'],
            'is_recommended' => (int) $shop['is_recommended'],
            'audit_status' => (int) $shop['audit_status'],
            'audit_status_text' => $this->getAuditStatusText((int) $shop['audit_status']),
            'audit_reason' => $shop['audit_reason'],
            'audited_at' => $shop['audited_at'],
            'status' => (int) $shop['status'],
            'status_text' => (int) $shop['status'] === 1 ? '营业中' : '已关闭',
            'open_time' => $shop['open_time'],
            'created_at' => $shop['created_at'],
            'updated_at' => $shop['updated_at'],
            'merchant' => [
                'username' => $shop['username'],
                'nickname' => $shop['nickname'],
                'phone' => $shop['phone'],
                'avatar' => $shop['user_avatar'],
            ],
        ];
    }

    /**
     * 审核状态文本
     */
    private function getAuditStatusText(int $status): string
    {
        $map = [
            0 => '待审核',
            1 => '审核通过',
            2 => '审核驳回',
        ];

        return $map[$status] ?? '未知状态';
    }
}
