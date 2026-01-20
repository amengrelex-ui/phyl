<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiRecharge extends Validate
{
    protected $rule = ['name' => 'require','bank_name' => 'require','bank_account' => 'require',
    ];

    protected $message = ['name.require' => '收款人姓名为必填项','bank_name.require' => '收款银行名称为必填项','bank_account.require' => '收款银行账号为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['name','bank_name','bank_account',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['name','bank_name','bank_account',]);
    }
}
