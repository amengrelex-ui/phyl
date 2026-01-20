<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiProduct extends Validate
{
    protected $rule = [
        'product_type' => 'require',
        'name'         => 'require',
        'image'        => 'require',
        'cycle'        => 'require',
        'proceeds'     => 'require',
    ];

    protected $message = [
        'product_type.require' => '产品类型 1:稳健产品; 2:收益产品;为必填项',
//        'product_type.number'  => '产品类型 1:稳健产品; 2:收益产品;需为数字',
        'name.require'         => '产品名称为必填项',
        'image.require'        => '产品图片地址为必填项',
        // 'price.require'        => '投资金额为必填项',
//        'price.number'         => '投资金额需为数字',
        'cycle.require'        => '周期-天为必填项',
//        'cycle.number'         => '周期-天需为数字',
        'proceeds.require'     => '收益百分比为必填项',
//        'proceeds.number'      => '收益百分比需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['product_type','name','image','price','cycle','proceeds',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['product_type','name','image','price','cycle','proceeds',]);
    }
}
