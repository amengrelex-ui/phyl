<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiUserPublicwelfare extends Validate
{
    protected $rule = [
        'name'         => 'require',
        'image'        => 'require',
        'price'         => 'require',
        // 'minimum_investment'         => 'require',
        // 'highest_investment'         => 'require',
        // 'product_content' =>'require',
    ];

    protected $message = [
        'name.require'         => '公益名称为必填项',
        'image.require'        => '图片地址为必填项',
        'price.require'                 => '公益金额为必填项',
        // 'minimum_investment.require'                 => '最低捐献公益金额为必填项',
        // 'highest_investment.require'                 => '最高捐献公益金额为必填项',
        // 'product_content.require'                 => '公益内容为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['name','image','price','product_content','minimum_investment','highest_investment']);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['name','image','price','product_content','minimum_investment','highest_investment']);
    }
}
