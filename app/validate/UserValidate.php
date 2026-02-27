<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 用户验证器
 */
class UserValidate extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'username'         => 'require|alphaDash|length:3,50|unique:users',
        'password'         => 'require|length:6,20',
        'phone'            => 'require|mobile|unique:users',
        'phone_only'       => 'require|mobile',
        'code'             => 'require|length:6',
        'confirm_password' => 'require|confirm:password',
    ];

    /**
     * 定义错误信息
     */
    protected $message = [
        'username.require'         => '用户名不能为空',
        'username.alphaDash'       => '用户名只能包含字母、数字、下划线和破折号',
        'username.length'          => '用户名长度为3-50个字符',
        'username.unique'          => '用户名已存在',
        'password.require'         => '密码不能为空',
        'password.length'          => '密码长度为6-20个字符',
        'phone.require'            => '手机号不能为空',
        'phone.mobile'             => '手机号格式不正确',
        'phone.unique'             => '手机号已被注册',
        'phone_only.require'       => '手机号不能为空',
        'phone_only.mobile'        => '手机号格式不正确',
        'code.require'             => '验证码不能为空',
        'code.length'              => '验证码为6位',
        'confirm_password.require' => '确认密码不能为空',
        'confirm_password.confirm' => '两次输入的密码不一致',
    ];

    /**
     * 定义验证场景
     */
    protected $scene = [
        'login'          => ['username' => 'require', 'password' => 'require'],
        'register'       => ['username', 'password', 'phone', 'code', 'confirm_password'],
        'reset_password' => ['phone_only' => 'phone_only', 'code', 'password', 'confirm_password'],
        'send_code'      => ['phone_only' => 'phone_only'],
    ];
}
