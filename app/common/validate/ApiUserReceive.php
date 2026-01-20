<?php

declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class ApiUserReceive extends Validate
{
    protected $rule = [
        'name' => 'require',
        'income_price' => 'require',
        'status' => 'require|number',
    ];

    protected $message = [
        'name.require' => '名称为必填项',
        'income_price.require' => '所得税为必填项',
        'income_price.number' => '所得税需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['name', 'income_price',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['name', 'income_price',]);
    }
}
