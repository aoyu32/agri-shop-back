<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品标签模型
 */
class ProductTag extends Model
{
    protected $name = 'product_tags';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    protected $dateFormat = 'Y-m-d H:i:s';
}
