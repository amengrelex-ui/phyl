<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiCheckTime extends Validate
{
    protected $rule = ['user_id' => 'require|number','create_time' => 'require|number','continuous_check_in_time' => 'require|number','integral' => 'require|number',
    ];

    protected $message = ['user_id.require' => '用户ID为必填项','user_id.number' => '用户ID需为数字','create_time.require' => '签到时间为必填项','create_time.number' => '签到时间需为数字','continuous_check_in_time.require' => '连续签到天数为必填项','continuous_check_in_time.number' => '连续签到天数需为数字','integral.require' => '当天签到获得的积分为必填项','integral.number' => '当天签到获得的积分需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['user_id','create_time','continuous_check_in_time','integral',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['user_id','create_time','continuous_check_in_time','integral',]);
    }
}
