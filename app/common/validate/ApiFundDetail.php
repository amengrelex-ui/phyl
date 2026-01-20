<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiFundDetail extends Validate
{
    protected $rule = ['data_type' => 'require|number','user_id' => 'require|number','price' => 'require|number','status' => 'require|number','remarks' => 'require',
    ];

    protected $message = ['data_type.require' => '数据类型 1:申请提款; 2:充值; 3:扣除; 4:下级返现;5:抽奖返现;为必填项','data_type.number' => '数据类型 1:申请提款; 2:充值; 3:扣除; 4:下级返现;5:抽奖返现;需为数字','user_id.require' => '用户ID为必填项','user_id.number' => '用户ID需为数字','price.require' => '金额为必填项','price.number' => '金额需为数字','status.require' => '状态 1:成功; 2:失败; 3:等待审核处理;为必填项','status.number' => '状态 1:成功; 2:失败; 3:等待审核处理;需为数字','remarks.require' => '备注为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['data_type','user_id','price','status','remarks',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['data_type','user_id','price','status','remarks',]);
    }
}
