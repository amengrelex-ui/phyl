<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiLuck extends Validate
{
    protected $rule = ['user_id' => 'require|number','award' => 'require','integral' => 'require|number','status' => 'require|number',
    ];

    protected $message = ['user_id.require' => '用户ID为必填项','user_id.number' => '用户ID需为数字','award.require' => '物品名称为必填项','integral.require' => '消耗积分为必填项','integral.number' => '消耗积分需为数字','status.require' => '是否中奖 1:中奖; 2:未中奖;为必填项','status.number' => '是否中奖 1:中奖; 2:未中奖;需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['user_id','award','integral','status',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['user_id','award','integral','status',]);
    }
}
