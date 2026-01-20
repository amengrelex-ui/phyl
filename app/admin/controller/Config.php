<?php
declare (strict_types = 1);

namespace app\admin\controller;

use think\facade\Request;
use think\facade\Validate;
use think\facade\Db;

class Config extends Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];
    
    // 系统配置
    public function index(){
        if(Request::isPost()){

            $rule = [
                'download_url|app下载链接'          => ['require'],           // 注册赠送积分
                'register_integral|注册赠送积分'          => ['require', 'number','lt:0'],           // 注册赠送积分
                'register_price|注册赠送金额'             => ['require', 'float','lt:0'],            // 注册赠送金额
                'register_parent_integral|注册上级赠送积分' => ['require', 'number','lt:0'],           // 注册上级赠送积分
                'brief_introduction|公司简介'           => ['require'],                     // 公司简介
                'culture|公司文化'                      => ['require'],                     // 公司文化
                'online_service|在线客服'               => ['require'],                     // 在线客服
                'legal_declaration|法律声明'            => ['require'],                     // 法律声明
                'subscription_certificate|认购证书'     => ['require'],                     // 认购证书
                'general_member_sign_integral|普通会员签到积分'        => ['require', 'number', 'lt:0'],      // 普通会员签到获得多少积分
                'member_head_sign_integral|会员团长签到获得积分'         => ['require', 'number', 'lt:0'],      // 会员团长签到获得多少积分
                'silver_head_sign_integral|白银团长签到获得积分'         => ['require', 'number', 'lt:0'],      // 白银签到获得多少积分
                'gold_head_sign_integral|黄金团长签到获得积分'           => ['require', 'number', 'lt:0'],      // 黄金签到获得多少积分
                'diamonds_head_sign_integral|钻石团长签到获得积分'       => ['require', 'number', 'lt:0'], // 钻石签到获得多少积分
                'star_head_sign_integral|星耀团长签到获得积分'           => ['require', 'number', 'lt:0'], // 星耀签到获得多少积分
                'generation_referrer|一级推荐奖励' => ['require', 'float', 'lt:0'], // 普通会员下级投资反点
                'second_generation_recommender|二级推荐奖励'    => ['require', 'float', 'lt:0'], // 会员团长下级投资反点
                'corporate_culture_pictures1|企业文化图片1'        => ['require'],
                'corporate_culture_pictures2|企业文化图片2'        => ['require'],
                'corporate_culture_pictures3|企业文化图片3'        => ['require'],
                'corporate_culture_pictures4|企业文化图片4'        => ['require'],
                'corporate_culture_pictures5|企业文化图片5'        => ['require'],
                'member_head_grow_up|会员团长成长值'                => ['require'],
                'silver_head_grow_up|白银团长成长值'                => ['require'],
                'gold_head_grow_up|黄金团长成长值'                  => ['require'],
                'diamonds_grow_up|钻石团长成长值'                   => ['require'],
                'star_head_grow_up|星耀团长成长值'                  => ['require'],
                'share_image|分享图片'                             => ['require'],
                'equity_certificate|股权证书'                      => ['require'],
                'team_rewards|团队奖励'                            => ['require'],
                'award_1|奖项1-物品名称'                            => ['require'],
                'award_1_type|奖项1-类型'                          => ['require'],
                'award_1_probability|奖项1-概率'                   => ['require'],
                'award_2|奖项2-物品名称'                            => ['require'],
                'award_2_type|奖项2-类型'                          => ['require'],
                'award_2_probability|奖项2-概率'                   => ['require'],
                'award_3|奖项3-物品名称'                            => ['require'],
                'award_3_type|奖项3-类型'                          => ['require'],
                'award_3_probability|奖项3-概率'                   => ['require'],
                'award_4|奖项4-物品名称'                            => ['require'],
                'award_4_type|奖项4-类型'                          => ['require'],
                'award_4_probability|奖项4-概率'                   => ['require'],
                'award_5|奖项4-物品名称'                            => ['require'],
                'award_5_type|奖项5-类型'                          => ['require'],
                'award_5_probability|奖项5-概率'                   => ['require'],
                'award_6|奖项6-物品名称'                            => ['require'],
                'award_6_type|奖项6-类型'                          => ['require'],
                'award_6_probability|奖项6-概率'                   => ['require'],
                'empty_probability|空奖项-概率'                     => ['require'],
                'general_member_lottery_integral|普通会员抽奖消耗积分'        => ['require', 'number', 'lt:0'],
                'member_head_lottery_integral|会员团长抽奖消耗积分'         => ['require', 'number', 'lt:0'],
                'silver_head_lottery_integral|白银团长抽奖消耗积分'         => ['require', 'number', 'lt:0'],
                'gold_head_lottery_integral|黄金团长抽奖消耗积分'           => ['require', 'number', 'lt:0'],
                'diamonds_head_lottery_integral|钻石团长抽奖消耗积分'       => ['require', 'number', 'lt:0'],
                'star_head_lottery_integral|星耀团长抽奖消耗积分'           => ['require', 'number', 'lt:0'],
                'notice|首页公告'           => ['require'],
                'min_withdraw|最低提现'           => ['require', 'number'],
            ];
            Validate::rule($rule);
            if (!Validate::check(Request::post())) {
                return $this->error('保存失败'.Validate::getError(),Request::root().'/config/index');
            }

            $check = [
                'register_integral'                 => '注册赠送积分',
                'register_price'                    => '注册赠送金额',
                'register_parent_integral'          => '注册上级赠送积分',
                'general_member_sign_integral'      => '普通会员签到获得多少积分',
                'member_head_sign_integral'         => '会员团长签到获得多少积分',
                'silver_head_sign_integral'         => '白银签到获得多少积分',
                'gold_head_sign_integral'           => '黄金签到获得多少积分',
                'diamonds_head_sign_integral'       => '钻石签到获得多少积分',
                'star_head_sign_integral'           => '星耀签到获得多少积分',
                'general_member_subordinate_profit' => '普通会员下级投资反点',
                'member_head_subordinate_profit'    => '会员团长下级投资反点',
                'silver_head_subordinate_profit'    => '白银下级投资反点',
                'gold_head_subordinate_profit'      => '黄金下级投资反点',
                'diamonds_subordinate_profit'       => '钻石下级投资反点',
                'star_head_subordinate_profit'      => '星耀下级投资反点',
                'general_member_secondary_profit'   => '普通会员二级投资反点',
                'member_head_secondary_profit'      => '会员团长二级投资反点',
                'silver_head_secondary_profit'      => '白银二级投资反点',
                'gold_head_secondary_profit'        => '黄金二级投资反点',
                'diamonds_secondary_profit'         => '钻石二级投资反点',
                'star_head_secondary_profit'        => '星耀二级投资反点',
            ];
            foreach ($check as $key => $value){
                if (0 > (float)Request::post($key,0)){
                    return $this->error('保存失败'.$value.'不能小于0',Request::root().'/config/index');
                }
            }


           set_web(Request::post());
           return  $this->success('保存成功',Request::root().'/config/index');
        }
        $products = Db::name('api_product')->select();
        return $this->fetch('',[
            'data' => config('web'),
            'products' => $products
        ]);
    }
}
