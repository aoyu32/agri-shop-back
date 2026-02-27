<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\User;
use app\validate\UserValidate;
use app\common\Response;
use app\common\Jwt;
use think\facade\Cache;
use think\exception\ValidateException;

/**
 * 用户认证控制器
 */
class AuthController extends BaseController
{
    /**
     * 用户登录
     */
    public function login()
    {
        try {
            // 获取请求参数（支持JSON和表单）
            $params = $this->request->param();

            // 提取需要的字段
            $username = $params['username'] ?? '';
            $password = $params['password'] ?? '';
            $remember = $params['remember'] ?? false;

            // 验证数据
            $validate = new UserValidate();
            if (!$validate->scene('login')->check(['username' => $username, 'password' => $password])) {
                return Response::validateError($validate->getError());
            }

            // 查找用户（支持用户名或手机号登录）
            $user = User::findByUsernameOrPhone($username);

            if (!$user) {
                return Response::error('用户不存在');
            }

            // 检查用户状态
            if ($user->status == 0) {
                return Response::error('账号已被禁用');
            }

            // 验证密码
            if (!$user->checkPassword($password)) {
                return Response::error('密码错误');
            }

            // 生成 JWT Token
            $expire = $remember ? 7 * 24 * 3600 : 24 * 3600;
            $token = Jwt::createToken($user->id, [
                'username' => $user->username,
                'role' => $user->role
            ], $expire);

            // 返回用户信息和token
            return Response::success([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'nickname' => $user->nickname,
                    'role' => $user->role,
                ]
            ], '登录成功');
        } catch (ValidateException $e) {
            return Response::validateError($e->getMessage());
        } catch (\Exception $e) {
            return Response::error('登录失败：' . $e->getMessage());
        }
    }

    /**
     * 用户注册
     */
    public function register()
    {
        try {
            // 获取请求参数
            $params = $this->request->param();

            $username = $params['username'] ?? '';
            $password = $params['password'] ?? '';
            $confirm_password = $params['confirm_password'] ?? '';
            $phone = $params['phone'] ?? '';
            $code = $params['code'] ?? '';
            $role = $params['role'] ?? 'consumer';

            // 验证数据
            $validate = new UserValidate();
            $data = [
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirm_password,
                'phone' => $phone,
                'code' => $code
            ];

            if (!$validate->scene('register')->check($data)) {
                return Response::validateError($validate->getError());
            }

            // 验证短信验证码
            $cacheCode = Cache::get('sms_code_' . $phone);
            if (!$cacheCode) {
                return Response::error('验证码已过期，请重新获取');
            }

            if ($cacheCode != $code) {
                return Response::error('验证码错误');
            }

            // 设置默认角色
            $role = in_array($role, ['consumer', 'merchant']) ? $role : 'consumer';

            // 创建用户
            $user = User::create([
                'username' => $username,
                'password' => $password,
                'phone' => $phone,
                'role' => $role,
                'status' => 1,
                'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            ]);

            // 删除验证码缓存
            Cache::delete('sms_code_' . $phone);

            return Response::success([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ]
            ], '注册成功');
        } catch (ValidateException $e) {
            return Response::validateError($e->getMessage());
        } catch (\Exception $e) {
            return Response::error('注册失败：' . $e->getMessage());
        }
    }

    /**
     * 发送短信验证码
     */
    public function sendCode()
    {
        try {
            // 获取手机号
            $phone = $this->request->param('phone');

            // 验证手机号
            $validate = new UserValidate();
            if (!$validate->scene('send_code')->check(['phone' => $phone])) {
                return Response::validateError($validate->getError());
            }

            // 检查发送频率（60秒内只能发送一次）
            $lastSendTime = Cache::get('sms_send_time_' . $phone);
            if ($lastSendTime && (time() - $lastSendTime) < 60) {
                return Response::error('发送过于频繁，请稍后再试');
            }

            // 生成6位随机验证码
            $code = sprintf('%06d', mt_rand(0, 999999));

            // 缓存验证码，有效期5分钟
            Cache::set('sms_code_' . $phone, $code, 300);
            Cache::set('sms_send_time_' . $phone, time(), 60);

            // TODO: 实际项目中这里应该调用短信服务商API发送短信
            // 开发环境直接返回验证码（生产环境需要删除）
            $debugMode = env('app.debug', false);

            return Response::success([
                'message' => '验证码已发送',
                'code' => $debugMode ? $code : null, // 仅开发环境返回验证码
            ], '验证码发送成功');
        } catch (ValidateException $e) {
            return Response::validateError($e->getMessage());
        } catch (\Exception $e) {
            return Response::error('发送失败：' . $e->getMessage());
        }
    }

    /**
     * 重置密码
     */
    public function resetPassword()
    {
        try {
            // 获取请求参数
            $params = $this->request->param();

            $phone = $params['phone'] ?? '';
            $code = $params['code'] ?? '';
            $password = $params['password'] ?? '';
            $confirm_password = $params['confirm_password'] ?? '';

            // 验证数据
            $validate = new UserValidate();
            $data = [
                'phone' => $phone,
                'code' => $code,
                'password' => $password,
                'confirm_password' => $confirm_password
            ];

            if (!$validate->scene('reset_password')->check($data)) {
                return Response::validateError($validate->getError());
            }

            // 验证短信验证码
            $cacheCode = Cache::get('sms_code_' . $phone);
            if (!$cacheCode) {
                return Response::error('验证码已过期，请重新获取');
            }

            if ($cacheCode != $code) {
                return Response::error('验证码错误');
            }

            // 查找用户
            $user = User::findByPhone($phone);
            if (!$user) {
                return Response::error('该手机号未注册');
            }

            // 更新密码
            $user->password = $password;
            $user->save();

            // 删除验证码缓存
            Cache::delete('sms_code_' . $phone);

            return Response::success([], '密码重置成功');
        } catch (ValidateException $e) {
            return Response::validateError($e->getMessage());
        } catch (\Exception $e) {
            return Response::error('重置失败：' . $e->getMessage());
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        try {
            // JWT 是无状态的，客户端删除 token 即可
            // 如果需要实现黑名单机制，可以将 token 加入黑名单缓存
            return Response::success([], '退出成功');
        } catch (\Exception $e) {
            return Response::error('退出失败：' . $e->getMessage());
        }
    }

    /**
     * 获取当前用户信息
     */
    public function getUserInfo()
    {
        try {
            // 获取token
            $token = $this->request->header('Authorization');
            $token = str_replace('Bearer ', '', $token);

            if (!$token) {
                return Response::unauthorized('未提供token');
            }

            // 验证并解析 JWT Token
            $userId = Jwt::getUserId($token);
            if (!$userId) {
                return Response::unauthorized('token已过期或无效');
            }

            // 获取用户信息
            $user = User::find($userId);
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
                    'role' => $user->role,
                    'gender' => $user->gender,
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取用户信息失败：' . $e->getMessage());
        }
    }
}
