<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiSubscribe extends Validate
{
    protected $rule = ['user_id' => 'require|number','product_id' => 'require|number','product_type' => 'require|number','name' => 'require','image' => 'require','price' => 'require|number','cycle' => 'require|number','end_time' => 'require','proceeds' => 'require|number','status' => 'require|number','cash_back_amount_per_day' => 'require|number','estimated_total_revenue' => 'require|number','amount_of_income_received' => 'require|number','username' => 'require','id_card' => 'require','order_number' => 'require','subscription_certificate' => 'require',
    ];

    protected $message = ['user_id.require' => '用户ID为必填项','user_id.number' => '用户ID需为数字','product_id.require' => '产品标识为必填项','product_id.number' => '产品标识需为数字','product_type.require' => '产品类型 1:爱国产品; 2:时代股份产品;为必填项','product_type.number' => '产品类型 1:爱国产品; 2:时代股份产品;需为数字','name.require' => '产品名称为必填项','image.require' => '产品图片为必填项','price.require' => '认购金额为必填项','price.number' => '认购金额需为数字','cycle.require' => '周期为必填项','cycle.number' => '周期需为数字','end_time.require' => '结束时间为必填项','proceeds.require' => '收益百分比为必填项','proceeds.number' => '收益百分比需为数字','status.require' => '产品状态 1:认购中; 2:已完成为必填项','status.number' => '产品状态 1:认购中; 2:已完成需为数字','cash_back_amount_per_day.require' => '每日现金返还金额为必填项','cash_back_amount_per_day.number' => '每日现金返还金额需为数字','estimated_total_revenue.require' => '预计收入为必填项','estimated_total_revenue.number' => '预计收入需为数字','amount_of_income_received.require' => '已收益金额为必填项','amount_of_income_received.number' => '已收益金额需为数字','username.require' => '真实姓名为必填项','id_card.require' => '身份证号码为必填项','order_number.require' => '认购编号为必填项','subscription_certificate.require' => '认购证书为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['user_id','product_id','product_type','name','image','price','cycle','end_time','proceeds','status','cash_back_amount_per_day','estimated_total_revenue','amount_of_income_received','username','id_card','order_number','subscription_certificate',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['user_id','product_id','product_type','name','image','price','cycle','end_time','proceeds','status','cash_back_amount_per_day','estimated_total_revenue','amount_of_income_received','username','id_card','order_number','subscription_certificate',]);
    }
}
