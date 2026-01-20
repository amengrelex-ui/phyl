<?php
declare (strict_types=1);

namespace app\common\validate;

use think\Validate;

class ApiUser extends Validate
{
    protected $rule = [
        'mobile'         => 'require|mobile',
        'password'       => 'require',
        'integral'       => 'require|number',
        'grow_up'        => 'require|number',
        'username'       => 'require',
        'id_card'        => 'require',
        'price'          => 'require|float',
    ];

    protected $message = [
        'mobile.require'         => '手机号为必填项',
        'password.require'       => '密码为必填项',
        'integral.require'       => '用户积分为必填项',
        'integral.number'        => '用户积分需为数字',
        'grow_up.require'        => '成长值为必填项',
        'grow_up.number'         => '成长值需为数字',
        'username.require'       => '用户真实姓名为必填项',
        'id_card.require'        => '用户身份证号码为必填项',
        'price.require'          => '可提现金额为必填项',
        'price.float'           => '可提现金额需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(
            [
                'mobile',
                'password',
                'parent_user_id',
                'integral',
                'grow_up',
                'username',
                'id_card',
                'price',
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
                'mobile',
                'parent_user_id',
                'username',
                'id_card',
            ]
        );
    }
}
