<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Response;
use app\model\AiConversation;
use app\model\Cart;
use app\model\CommentLike;
use app\model\CommunityPost;
use app\model\Favorite;
use app\model\Footprint;
use app\model\Notification;
use app\model\Order;
use app\model\OrderRefund;
use app\model\OrderReview;
use app\model\PostComment;
use app\model\PostLike;
use app\model\Shop;
use app\model\User;
use app\model\UserAddress;

/**
 * 管理后台用户管理控制器
 */
class AdminUserController extends BaseController
{
    /**
     * 用户列表
     */
    public function list()
    {
        try {
            $keyword = trim((string) $this->request->param('keyword', ''));
            $role = (string) $this->request->param('role', '');
            $status = $this->request->param('status', '');
            $page = (int) $this->request->param('page', 1);
            $pageSize = (int) $this->request->param('page_size', 10);

            $query = User::whereIn('role', ['consumer', 'merchant']);

            if ($keyword !== '') {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->whereOr('username', 'like', "%{$keyword}%")
                        ->whereOr('nickname', 'like', "%{$keyword}%")
                        ->whereOr('phone', 'like', "%{$keyword}%");
                });
            }

            if (in_array($role, ['consumer', 'merchant'], true)) {
                $query->where('role', $role);
            }

            if ($status !== '' && in_array((int) $status, [0, 1], true)) {
                $query->where('status', (int) $status);
            }

            $users = $query->order('id', 'desc')->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);

            $list = [];
            foreach ($users->items() as $user) {
                $list[] = $this->formatUser($user);
            }

            return Response::success([
                'list' => $list,
                'total' => $users->total(),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取用户列表失败：' . $e->getMessage());
        }
    }

    /**
     * 用户详情
     */
    public function detail()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('用户ID不能为空');
            }

            $user = User::find($id);
            if (!$user || !in_array($user->role, ['consumer', 'merchant'], true)) {
                return Response::error('用户不存在');
            }

            return Response::success([
                'user' => $this->formatUser($user, true),
            ]);
        } catch (\Exception $e) {
            return Response::error('获取用户详情失败：' . $e->getMessage());
        }
    }

    /**
     * 新增用户
     */
    public function create()
    {
        try {
            $username = trim((string) $this->request->param('username'));
            $password = (string) $this->request->param('password');
            $phone = trim((string) $this->request->param('phone'));
            $nickname = trim((string) $this->request->param('nickname', ''));
            $avatar = trim((string) $this->request->param('avatar', ''));
            $role = (string) $this->request->param('role', 'consumer');
            $status = (int) $this->request->param('status', 1);
            $address = trim((string) $this->request->param('address', ''));
            $bio = trim((string) $this->request->param('bio', ''));

            if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
                return Response::validateError('用户名长度需为3-50个字符');
            }
            if (strlen($password) < 6 || strlen($password) > 20) {
                return Response::validateError('密码长度需为6-20个字符');
            }
            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                return Response::validateError('请输入正确的手机号');
            }
            if (!in_array($role, ['consumer', 'merchant'], true)) {
                return Response::validateError('用户角色不正确');
            }
            if (User::where('username', $username)->find()) {
                return Response::validateError('用户名已存在');
            }
            if (User::where('phone', $phone)->find()) {
                return Response::validateError('手机号已存在');
            }

            $user = User::create([
                'username' => $username,
                'password' => $password,
                'phone' => $phone,
                'nickname' => $nickname ?: $username,
                'avatar' => $avatar,
                'role' => $role,
                'status' => in_array($status, [0, 1], true) ? $status : 1,
                'address' => $address,
                'bio' => $bio,
            ]);

            return Response::success([
                'user' => $this->formatUser($user, true),
            ], '新增用户成功');
        } catch (\Exception $e) {
            return Response::error('新增用户失败：' . $e->getMessage());
        }
    }

    /**
     * 更新用户
     */
    public function update()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('用户ID不能为空');
            }

            $user = User::find($id);
            if (!$user || !in_array($user->role, ['consumer', 'merchant'], true)) {
                return Response::error('用户不存在');
            }

            $username = trim((string) $this->request->param('username', $user->username));
            $password = (string) $this->request->param('password', '');
            $phone = trim((string) $this->request->param('phone', $user->phone));
            $nickname = trim((string) $this->request->param('nickname', $user->nickname));
            $avatar = trim((string) $this->request->param('avatar', $user->avatar));
            $role = (string) $this->request->param('role', $user->role);
            $status = (int) $this->request->param('status', $user->status);
            $address = trim((string) $this->request->param('address', $user->address));
            $bio = trim((string) $this->request->param('bio', $user->bio));

            if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
                return Response::validateError('用户名长度需为3-50个字符');
            }
            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                return Response::validateError('请输入正确的手机号');
            }
            if (!in_array($role, ['consumer', 'merchant'], true)) {
                return Response::validateError('用户角色不正确');
            }
            if (User::where('username', $username)->where('id', '<>', $id)->find()) {
                return Response::validateError('用户名已存在');
            }
            if (User::where('phone', $phone)->where('id', '<>', $id)->find()) {
                return Response::validateError('手机号已存在');
            }
            if ($password !== '' && (strlen($password) < 6 || strlen($password) > 20)) {
                return Response::validateError('密码长度需为6-20个字符');
            }

            $user->username = $username;
            $user->phone = $phone;
            $user->nickname = $nickname ?: $username;
            $user->avatar = $avatar;
            $user->role = $role;
            $user->status = in_array($status, [0, 1], true) ? $status : 1;
            $user->address = $address;
            $user->bio = $bio;
            if ($password !== '') {
                $user->password = $password;
            }
            $user->save();

            return Response::success([
                'user' => $this->formatUser($user, true),
            ], '更新用户成功');
        } catch (\Exception $e) {
            return Response::error('更新用户失败：' . $e->getMessage());
        }
    }

    /**
     * 删除用户
     */
    public function delete()
    {
        try {
            $id = (int) $this->request->param('id');
            $adminUser = $this->request->adminUser;

            if (!$id) {
                return Response::validateError('用户ID不能为空');
            }
            if ($adminUser && (int) $adminUser->id === $id) {
                return Response::error('不能删除当前登录管理员');
            }

            $user = User::find($id);
            if (!$user || !in_array($user->role, ['consumer', 'merchant'], true)) {
                return Response::error('用户不存在');
            }

            $relations = $this->getUserRelationCounts($id);
            $usedRelations = [];
            foreach ($relations as $label => $count) {
                if ($count > 0) {
                    $usedRelations[] = $label;
                }
            }

            if (!empty($usedRelations)) {
                return Response::error('该用户存在业务数据，无法删除，请改为禁用。关联数据：' . implode('、', $usedRelations));
            }

            $user->delete();

            return Response::success([], '删除用户成功');
        } catch (\Exception $e) {
            return Response::error('删除用户失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化用户输出
     */
    private function formatUser(User $user, bool $withExtra = false): array
    {
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'nickname' => $user->nickname,
            'address' => $user->address,
            'bio' => $user->bio,
            'role' => $user->role,
            'role_text' => $user->role === 'merchant' ? '农户' : '消费者',
            'status' => (int) $user->status,
            'status_text' => (int) $user->status === 1 ? '正常' : '禁用',
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($withExtra || $user->role === 'merchant') {
            $data['shop_count'] = Shop::where('user_id', $user->id)->count();
            $data['order_count'] = Order::where('user_id', $user->id)->count();
            $data['post_count'] = CommunityPost::where('user_id', $user->id)->count();
        }

        return $data;
    }

    /**
     * 用户关联数据统计
     */
    private function getUserRelationCounts(int $userId): array
    {
        return [
            '店铺' => Shop::where('user_id', $userId)->count(),
            '订单' => Order::where('user_id', $userId)->count(),
            '退款' => OrderRefund::where('user_id', $userId)->count(),
            '评价' => OrderReview::where('user_id', $userId)->count(),
            '收货地址' => UserAddress::where('user_id', $userId)->count(),
            '购物车' => Cart::where('user_id', $userId)->count(),
            '收藏' => Favorite::where('user_id', $userId)->count(),
            '足迹' => Footprint::where('user_id', $userId)->count(),
            'AI会话' => AiConversation::where('user_id', $userId)->count(),
            '社区帖子' => CommunityPost::where('user_id', $userId)->count(),
            '社区评论' => PostComment::where('user_id', $userId)->count(),
            '帖子点赞' => PostLike::where('user_id', $userId)->count(),
            '评论点赞' => CommentLike::where('user_id', $userId)->count(),
            '通知' => Notification::where('user_id', $userId)->count(),
        ];
    }
}
