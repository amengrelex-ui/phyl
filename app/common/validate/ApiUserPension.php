<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiUserPension extends Validate
{
    protected $rule = ['id_card' => 'require',
    ];

    protected $message = ['id_card.require' => '用户身份证号码为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['id_card',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['id_card',]);
    }
}
