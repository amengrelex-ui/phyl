<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiLevel extends Validate
{
    protected $rule = ['name' => 'require','remark' => 'require',
    ];

    protected $message = ['name.require' => '档位为必填项','remark.require' => '描述为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['name','remark',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['name','remark',]);
    }
}
