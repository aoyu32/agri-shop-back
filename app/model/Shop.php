<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 店铺模型
 */
class Shop extends Model
{
    protected $name = 'shops';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
