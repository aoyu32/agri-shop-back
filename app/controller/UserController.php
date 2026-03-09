<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\User;
use app\common\Response;
use app\common\Jwt;

/**
 * 用户信息管理控制器
 */
class UserController extends BaseController
{
    /**
     * 获取用户信息
     */
    public function info()
    {
        try {
            $userId = $this->request->userId;

            $user = User::field('id,username,phone,avatar,nickname,address,bio,role,status,created_at')
                ->find($userId);

            if (!$user) {
                return Response::error('用户不存在');
            }

            return Response::success([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'nickname' => $user->nickname,
                    'address' => $user->address,
                    'bio' => $user->bio,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取用户信息失败：' . $e->getMessage());
        }
    }

    /**
     * 更新用户信息
     */
    public function update()
    {
        try {
            $userId = $this->request->userId;

            $user = User::find($userId);
            if (!$user) {
                return Response::error('用户不存在');
            }

            // 获取要更新的字段
            $nickname = $this->request->param('nickname');
            $avatar = $this->request->param('avatar');
            $address = $this->request->param('address');
            $bio = $this->request->param('bio');

            // 验证昵称
            if ($nickname !== null) {
                if (empty($nickname)) {
                    return Response::validateError('昵称不能为空');
                }

                if (mb_strlen($nickname) < 2 || mb_strlen($nickname) > 50) {
                    return Response::validateError('昵称长度为2-50个字符');
                }

                $user->nickname = $nickname;
            }

            // 更新其他字段
            if ($avatar !== null) {
                $user->avatar = $avatar;
            }

            if ($address !== null) {
                if (mb_strlen($address) > 200) {
                    return Response::validateError('地址长度不能超过200个字符');
                }
                $user->address = $address;
            }

            if ($bio !== null) {
                if (mb_strlen($bio) > 500) {
                    return Response::validateError('个人简介长度不能超过500个字符');
                }
                $user->bio = $bio;
            }

            $user->save();

            return Response::success([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'nickname' => $user->nickname,
                    'address' => $user->address,
                    'bio' => $user->bio,
                    'role' => $user->role
                ]
            ], '信息更新成功');
        } catch (\Exception $e) {
            return Response::error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 更新头像
     */
    public function updateAvatar()
    {
        try {
            $userId = $this->request->userId;
            $avatar = $this->request->param('avatar');

            if (empty($avatar)) {
                return Response::validateError('头像地址不能为空');
            }

            $user = User::find($userId);
            if (!$user) {
                return Response::error('用户不存在');
            }

            $user->avatar = $avatar;
            $user->save();

            return Response::success([
                'avatar' => $avatar
            ], '头像更新成功');
        } catch (\Exception $e) {
            return Response::error('更新头像失败：' . $e->getMessage());
        }
    }

    /**
     * 修改密码
     */
    public function changePassword()
    {
        try {
            $userId = $this->request->userId;
            $oldPassword = $this->request->param('old_password');
            $newPassword = $this->request->param('new_password');
            $confirmPassword = $this->request->param('confirm_password');

            // 验证参数
            if (empty($oldPassword)) {
                return Response::validateError('请输入原密码');
            }

            if (empty($newPassword)) {
                return Response::validateError('请输入新密码');
            }

            if (strlen($newPassword) < 6 || strlen($newPassword) > 20) {
                return Response::validateError('新密码长度为6-20个字符');
            }

            if ($newPassword !== $confirmPassword) {
                return Response::validateError('两次输入的密码不一致');
            }

            if ($oldPassword === $newPassword) {
                return Response::validateError('新密码不能与原密码相同');
            }

            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return Response::error('用户不存在');
            }

            // 验证原密码
            if (!password_verify($oldPassword, $user->password)) {
                return Response::validateError('原密码错误');
            }

            // 更新密码（模型会自动加密）
            $user->password = $newPassword;
            $user->save();

            return Response::success([], '密码修改成功，请重新登录');
        } catch (\Exception $e) {
            return Response::error('修改密码失败：' . $e->getMessage());
        }
    }

    /**
     * 更换手机号
     */
    public function changePhone()
    {
        try {
            $userId = $this->request->userId;
            $newPhone = $this->request->param('phone');
            $code = $this->request->param('code');

            // 验证参数
            if (empty($newPhone)) {
                return Response::validateError('请输入新手机号');
            }

            if (!preg_match('/^1[3-9]\d{9}$/', $newPhone)) {
                return Response::validateError('请输入正确的手机号');
            }

            if (empty($code)) {
                return Response::validateError('请输入验证码');
            }

            // TODO: 验证验证码（需要实现验证码功能）
            // 这里暂时跳过验证码验证

            // 检查手机号是否已被使用
            $exists = User::where('phone', $newPhone)
                ->where('id', '<>', $userId)
                ->find();

            if ($exists) {
                return Response::validateError('该手机号已被其他用户使用');
            }

            // 更新手机号
            $user = User::find($userId);
            if (!$user) {
                return Response::error('用户不存在');
            }

            $user->phone = $newPhone;
            $user->save();

            return Response::success([
                'phone' => $newPhone
            ], '手机号更换成功');
        } catch (\Exception $e) {
            return Response::error('更换手机号失败：' . $e->getMessage());
        }
    }

    /**
     * 注销账号
     */
    public function deleteAccount()
    {
        try {
            $userId = $this->request->userId;
            $password = $this->request->param('password');

            if (empty($password)) {
                return Response::validateError('请输入密码以确认注销');
            }

            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return Response::error('用户不存在');
            }

            // 验证密码
            if (!password_verify($password, $user->password)) {
                return Response::validateError('密码错误');
            }

            // 软删除或标记为已注销
            $user->status = 'deleted';
            $user->save();

            // 也可以选择物理删除
            // $user->delete();

            return Response::success([], '账号注销成功');
        } catch (\Exception $e) {
            return Response::error('注销账号失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户统计信息
     */
    public function statistics()
    {
        try {
            $userId = $this->request->userId;

            // 获取各种统计数据
            $orderCount = \app\model\Order::where('user_id', $userId)->count();
            $favoriteCount = \app\model\Favorite::where('user_id', $userId)->count();
            $footprintCount = \app\model\Footprint::where('user_id', $userId)->count();
            $cartCount = \app\model\Cart::where('user_id', $userId)->count();

            return Response::success([
                'statistics' => [
                    'order_count' => $orderCount,
                    'favorite_count' => $favoriteCount,
                    'footprint_count' => $footprintCount,
                    'cart_count' => $cartCount
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取统计信息失败：' . $e->getMessage());
        }
    }
}
