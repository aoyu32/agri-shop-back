<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品规格模型
 */
class ProductSpec extends Model
{
    protected $name = 'product_specs';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
