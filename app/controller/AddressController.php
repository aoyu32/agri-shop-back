<?php

namespace app\controller;

use app\BaseController;
use app\model\UserAddress;
use app\common\Response;
use think\facade\Request;

class AddressController extends BaseController
{
    /**
     * 获取用户地址列表
     */
    public function index()
    {
        $userId = $this->request->userId;

        $addresses = UserAddress::getUserAddresses($userId);

        return Response::success([
            'list' => $addresses
        ]);
    }

    /**
     * 获取地址详情
     */
    public function read($id)
    {
        $userId = $this->request->userId;

        $address = UserAddress::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$address) {
            return Response::error('地址不存在');
        }

        return Response::success($address);
    }

    /**
     * 添加地址
     */
    public function save()
    {
        $userId = $this->request->userId;
        $data = $this->request->post();

        // 验证必填字段
        if (empty($data['receiver_name'])) {
            return Response::error('请输入收货人姓名');
        }
        if (empty($data['receiver_phone'])) {
            return Response::error('请输入手机号');
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $data['receiver_phone'])) {
            return Response::error('请输入正确的手机号');
        }
        if (empty($data['detail_address'])) {
            return Response::error('请输入详细地址');
        }

        // 构建完整地址
        $fullAddress = '';
        if (!empty($data['province_name'])) {
            $fullAddress .= $data['province_name'] . ' ';
        }
        if (!empty($data['city_name'])) {
            $fullAddress .= $data['city_name'] . ' ';
        }
        if (!empty($data['district_name'])) {
            $fullAddress .= $data['district_name'] . ' ';
        }
        $fullAddress .= $data['detail_address'];

        $addressData = [
            'user_id'        => $userId,
            'receiver_name'  => $data['receiver_name'],
            'receiver_phone' => $data['receiver_phone'],
            'province_code'  => $data['province_code'] ?? '',
            'province_name'  => $data['province_name'] ?? '',
            'city_code'      => $data['city_code'] ?? '',
            'city_name'      => $data['city_name'] ?? '',
            'district_code'  => $data['district_code'] ?? '',
            'district_name'  => $data['district_name'] ?? '',
            'detail_address' => $data['detail_address'],
            'full_address'   => $fullAddress,
            'is_default'     => isset($data['is_default']) ? (int)$data['is_default'] : 0,
        ];

        // 如果设为默认，先取消其他默认地址
        if ($addressData['is_default']) {
            UserAddress::where('user_id', $userId)->update(['is_default' => 0]);
        }

        $address = UserAddress::create($addressData);

        return Response::success($address, '地址添加成功');
    }

    /**
     * 更新地址
     */
    public function update($id)
    {
        $userId = $this->request->userId;
        $data = $this->request->post();

        $address = UserAddress::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$address) {
            return Response::error('地址不存在');
        }

        // 验证必填字段
        if (empty($data['receiver_name'])) {
            return Response::error('请输入收货人姓名');
        }
        if (empty($data['receiver_phone'])) {
            return Response::error('请输入手机号');
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $data['receiver_phone'])) {
            return Response::error('请输入正确的手机号');
        }
        if (empty($data['detail_address'])) {
            return Response::error('请输入详细地址');
        }

        // 构建完整地址
        $fullAddress = '';
        if (!empty($data['province_name'])) {
            $fullAddress .= $data['province_name'] . ' ';
        }
        if (!empty($data['city_name'])) {
            $fullAddress .= $data['city_name'] . ' ';
        }
        if (!empty($data['district_name'])) {
            $fullAddress .= $data['district_name'] . ' ';
        }
        $fullAddress .= $data['detail_address'];

        $addressData = [
            'receiver_name'  => $data['receiver_name'],
            'receiver_phone' => $data['receiver_phone'],
            'province_code'  => $data['province_code'] ?? '',
            'province_name'  => $data['province_name'] ?? '',
            'city_code'      => $data['city_code'] ?? '',
            'city_name'      => $data['city_name'] ?? '',
            'district_code'  => $data['district_code'] ?? '',
            'district_name'  => $data['district_name'] ?? '',
            'detail_address' => $data['detail_address'],
            'full_address'   => $fullAddress,
            'is_default'     => isset($data['is_default']) ? (int)$data['is_default'] : 0,
        ];

        // 如果设为默认，先取消其他默认地址
        if ($addressData['is_default']) {
            UserAddress::where('user_id', $userId)
                ->where('id', '<>', $id)
                ->update(['is_default' => 0]);
        }

        $address->save($addressData);

        return Response::success($address, '地址更新成功');
    }

    /**
     * 删除地址
     */
    public function delete($id)
    {
        $userId = $this->request->userId;

        $result = UserAddress::deleteAddress($userId, $id);

        if ($result) {
            return Response::success(null, '地址删除成功');
        }

        return Response::error('地址删除失败');
    }

    /**
     * 设置默认地址
     */
    public function setDefault($id)
    {
        $userId = $this->request->userId;

        $address = UserAddress::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$address) {
            return Response::error('地址不存在');
        }

        UserAddress::setDefault($userId, $id);

        return Response::success(null, '设置成功');
    }

    /**
     * 获取默认地址
     */
    public function getDefault()
    {
        $userId = $this->request->userId;

        $address = UserAddress::getDefaultAddress($userId);

        if (!$address) {
            return Response::error('暂无默认地址', 404);
        }

        return Response::success($address);
    }
}
