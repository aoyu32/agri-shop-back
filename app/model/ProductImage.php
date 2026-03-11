<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品图片模型
 */
class ProductImage extends Model
{
    protected $name = 'product_images';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    protected $dateFormat = 'Y-m-d H:i:s';
}
