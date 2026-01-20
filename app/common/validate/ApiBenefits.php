<?php
declare (strict_types=1);

namespace app\common\validate;

use think\Validate;

class ApiAccount extends Validate
{
    protected $rule = [
        'id'         => 'require|number',
        'account_name'       => 'require',
        'user_id'       => 'require|number',
        'username'        => 'require',
        'account_number'       => 'require',
        'name_of_deposit_bank'        => 'require',
    ];

    protected $message = [
        'id.require'         => 'id为必填项',
        'account_name.require'       => '开户名称为必填项',
        'user_id.require'       => '用户id为必填项',
        'user_id.number'        => '用户id需为数字',
        'username.require'       => '用户真实姓名为必填项',
        'account_number.require'        => '开户号码为必填项',
        'account_number.number'        => '开户号码需为数字',
        'name_of_deposit_bank.require'          => '开户行名称为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(
            [
                'id',
                'account_name',
                'user_id',
                'username',
                'account_number',
                'name_of_deposit_bank',
                'cash_advance',
            ]
        );
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(
            [
                'id',
                'account_name',
                'user_id',
                'username',
                'account_number',
                'name_of_deposit_bank',
                'cash_advance',
            ]
        );
    }
}
