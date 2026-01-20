<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiPaylist extends Validate
{
    protected $rule = ['mchid' => 'require','appkey' => 'require','max' => 'require','status' => 'require|number',
    ];

    protected $message = ['mchid.require' => '商户ID为必填项','appkey.require' => '密钥为必填项','max.require' => '最大金额为必填项','status.require' => '状态为必填项','status.number' => '状态需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['mchid','appkey','max','status',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['mchid','appkey','max','status',]);
    }
}
