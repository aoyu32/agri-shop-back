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
    protected $autoWriteTimestamp = 'created_at';
    protected $createTime = 'created_at';
    protected $updateTime = false;
}
