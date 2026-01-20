<?php

namespace app\api\controller;


use think\captcha\facade\Captcha;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Validate;
use think\Request;

class Temp extends \app\AdminBaseController
{
    // 情况0、清除所有用户的稳健产品上级返点
    public function handle0(){
        $list = Db::query('select * from cloud_times_api_subscription where delete_time is null');
        foreach ($list as $v){
            Db::name('api_subscription')->where('id', $v['id'])->where('product_type', 1)->update([
                'amount_of_income_received' => 0
            ]);
        }
    }
    
    public function check(){
        $list = Db::name('api_user')->where('is_update', 1)->select();
        foreach ($list as $user){
            echo "user_id:".$user['id']." price:".$user['price']." cash_advance:".$user['cash_advance']."<br>";
        }
    }
    
    public function product_scheduled_task($product = [])
    {
        if (!$product){
            $product = Db::query("select * from cloud_times_api_subscription where UNIX_TIMESTAMP(DATE(create_time))<".strtotime('2022-4-6')." and status=1 and delete_time is null order by id asc");
        }
        $this_date = date('Y-m-d', time() + (86400 * \request()->param('num')));
        Db::startTrans();
        try{
            foreach ($product as $key => $value) {
                $end_time = date('Y-m-d', strtotime($value['end_time']));
                $create_time = strtotime(date('Y-m-d', strtotime($value['create_time'])));
                $subscription_update = ['update_time' => $this_date];
                $user_update = [];
                $fund_price = 0;
                if ($value['product_type'] == 1) {
                    if($value['status'] == 2){
                        continue;
                    }
                    
                    $has_gt_produt = Db::name('api_subscription')->where('delete_time IS NULL')->where('price', '>', $value['price'])->where('user_id', $value['user_id'])->count();
                    $diff = round((strtotime(date('Y-m-d')) - strtotime('2022-04-06'))/86400);
                    
                    if ((int)($value['cash_back_amount_per_day']*$diff) <= (int)($value['price']*$value['proceeds']/100/2)) {
                        $user_update = [
                            'cash_advance' => Db::raw(
                                'cash_advance+'.$value['cash_back_amount_per_day']
                            ),
                        ];
                        $fund_price = $value['cash_back_amount_per_day'];
                        
                    } else {
                        if($has_gt_produt){
                            $user_update = [
                                'cash_advance'=> Db::raw(
                                    'cash_advance-'.($value['cash_back_amount_per_day']*($value['cycle']-1))
                                ),
                                'price' => Db::raw(
                                    'price+'.($value['price']/2).'+'.($value['cash_back_amount_per_day']*($value['cycle']-1))
                                )
                            ];
                        }
                        $subscription_update['status'] = 2;
                        // if($key ==1){
                        //     exit;
                        // } 
                    }
                }
                $user_update['update_time'] = $this_date;
                Db::name('api_user')
                  ->where('id', $value['user_id'])
                  ->update($user_update);
                if($value['product_type'] == 2){
                    $subscription_update['amount_of_income_received'] = Db::raw(
                        'amount_of_income_received+'.$value['cash_back_amount_per_day']
                    );
                    Db::name('api_subscription')
                    ->where('id', $value['id'])
                    ->where('delete_time IS NULL')
                    ->update($subscription_update);
                } else {
                    Db::name('api_subscription')
                    ->where('id', $value['id'])
                    ->where('delete_time IS NULL')
                    ->update($subscription_update);
                }
                if(!$fund_price){
                    continue;
                }
                Db::name('api_fund_detail')
                  ->insert(
                      [
                          'data_type'   => 7,
                          'user_id'     => $value['user_id'],
                          'price'       => $fund_price,
                          'status'      => 1,
                          'remarks'     => '认购编号:'.$value['order_number'],
                          'create_time' => date('Y-m-d H:i:s'),
                          'update_time' => date('Y-m-d H:i:s'),
                      ]
                  );
            }
            
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败".$e->getMessage().$e->getLine();
        }
        
    }
    
    // 情况2、购买过多个产品，但都是同一种产品的
    public function handle4(){
        $list = Db::query("SELECT *,COUNT(user_id) FROM cloud_times_api_subscription WHERE delete_time IS null GROUP BY user_id HAVING COUNT(user_id)>1");
        // echo count($list); exit;
        Db::startTrans();
        try{
            $i = 0;
            foreach ($list as $v){
                $count = Db::name('api_subscription')->where('user_id', $v['user_id'])->where('product_type', '<>', 2)->whereNull('delete_time')->count();
                if($count){
                    continue;
                } else {
                    $i++;
                    echo $v['user_id']."<br>";
                }
            }
            echo $i;
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            echo $e->getMessage().$e->getLine();
        }
    }
    
    // 情况2、购买过多个产品，但都是同一种产品的
    public function handle3(){
        $list = Db::query("SELECT *,COUNT(user_id) FROM cloud_times_api_subscription WHERE delete_time IS null GROUP BY user_id HAVING COUNT(user_id)>1");
        // echo count($list); exit;
        Db::startTrans();
        try{
            $i = 0;
            foreach ($list as $v){
                $count = Db::name('api_subscription')->where('user_id', $v['user_id'])->where('product_type', '<>', 2)->whereNull('delete_time')->count();
                
                if($count){
                    $subs = Db::name('api_subscription')->where('user_id', $v['user_id'])->whereNull('delete_time')->select();
                    
                    $sum = Db::name('api_fund_detail')->where('data_type', 5)->where('user_id', $v['user_id'])->sum('price');
                    // echo $sum." ";
                    $sum1 = Db::name('api_fund_detail')->where('data_type', 1)->where('user_id', $v['user_id'])->where('status', 1)->sum('price');
                    // echo $sum1;
                    Db::name('api_user')->where('id', $v['user_id'])->update([
                        'price' => $sum - $sum1
                    ]);
                    foreach ($subs as $v){
                        $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                    
                        // echo "user_id:".$user['id']." price:".($sum-$sum1+($v['price']/2)+($v['cash_back_amount_per_day']*$v['cycle']/2))." cash_advance:".$user['cash_advance']." price:".$v['price']." create_time:".$v['create_time']." cash_back_amount_per_day:".($v['price']*$v['proceeds']/100/2/($v['cycle']-1))."<br>";
                        
                        Db::name('api_user')->where('id', $v['user_id'])->update([
                            'price' => Db::raw(
                                'price+'.(($v['price']/2)+($v['cash_back_amount_per_day']*$v['cycle']/2))
                            ),
                            'cash_advance' => 0,
                            'is_update'     => 1
                        ]);
                        Db::name('api_subscription')->where('id', $v['id'])->update([
                            'cash_back_amount_per_day' => ($v['price']*$v['proceeds']/100/2/($v['cycle']-1)),
                            'status' => 1
                        ]);
                    }
                }
            }
            echo $i."执行成功"; 
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            echo $e->getMessage().$e->getLine();
        }
    }
    
    // 情况1、只购买过一个产品
    public function handle2(){
        $list = Db::query("SELECT *,COUNT(user_id) FROM cloud_times_api_subscription WHERE delete_time IS null GROUP BY user_id HAVING COUNT(user_id)=1");
        // echo count($list);
        Db::startTrans();
        try{
            foreach ($list as $v){
                // if($v['product_type'] == 1 && $v['status'] ==2 && strtotime(date('Y-m-d', strtotime($v['create_time']))) < strtotime(date("Y-m-d"))){
                //     $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                //     echo "user_id:".$user['id']." price:".$user['price']." cash_advance:".$user['cash_advance']." price:".$v['price']."<br>";
                // }
                // print_r($v); 
                if($v['product_type'] == 1 && $v['status'] ==2 && strtotime(date('Y-m-d', strtotime($v['create_time']))) < strtotime(date("Y-m-d"))){
                    Db::name('api_user')->where('id', $v['user_id'])->update([
                        'price' => Db::raw('price-'.($v['price']/2).'-'.($v['cash_back_amount_per_day']*$v['cycle']/2)),
                        'cash_advance' => 0,
                        'is_update'     => 1
                    ]);
                    Db::name('api_subscription')->where('id', $v['id'])->update([
                        'cash_back_amount_per_day' => ($v['price']*$v['proceeds']/100/2/($v['cycle']-1)),
                        'status' => 1
                    ]);
                }
            }
            echo "执行成功"; 
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            echo $e->getMessage().$e->getLine();
        }
        
    }
    
    // 情况2、购买过多个产品，但都是同一种产品的
    public function handle1(){
        $list = Db::query("SELECT *,COUNT(user_id) FROM cloud_times_api_subscription WHERE delete_time IS null GROUP BY user_id HAVING COUNT(user_id)=1");
        
        Db::startTrans();
        try{
            foreach ($list as $v){
                if($v['product_type'] == 1 && $v['status'] ==1 && strtotime(date('Y-m-d', strtotime($v['create_time']))) < strtotime(date("Y-m-d"))){
                    $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                    
                    $sum = Db::name('api_fund_detail')->where('data_type', 5)->where('user_id', $v['user_id'])->sum('price');
                    $sum1 = Db::name('api_fund_detail')->where('data_type', 1)->where('user_id', $v['user_id'])->where('status', 1)->sum('price');
                    // echo "user_id:".$user['id']." price:".($sum+($v['price']/2)+($v['cash_back_amount_per_day']*$v['cycle']/2))." cash_advance:".$user['cash_advance']." price:".$v['price']." create_time:".$v['create_time']." cash_back_amount_per_day:".($v['price']*$v['proceeds']/100/2/($v['cycle']-1))."<br>";
                    
                    Db::name('api_user')->where('id', $v['user_id'])->update([
                        'price' => $sum-$sum1+($v['price']/2)+($v['cash_back_amount_per_day']*$v['cycle']/2),
                        'cash_advance' => 0,
                        'is_update'     => 1
                    ]);
                    Db::name('api_subscription')->where('id', $v['id'])->update([
                        'cash_back_amount_per_day' => ($v['price']*$v['proceeds']/100/2/($v['cycle']-1))
                    ]);
                }
            }
            echo "执行成功"; 
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            echo $e->getMessage().$e->getLine();
        }
    }
    
    public function cash(){
        $list = Db::name('api_fund_detail')->where('data_type', 1)->where('status', 3)->order('id desc')->select();
        
        Db::startTrans();
        try {
            foreach ($list as $k => $v){
                $result = Db::name('api_fund_detail')
                ->where('id',$v['id'])
                ->find();
                $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('price',(float)$result['price'])
                ->update();
                $result = Db::name('api_fund_detail')
                ->where('id',$v['id'])
                ->update([
                    'status' => 2
                ]);
                break;
            }
            echo "执行成功"; 
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            return returnJson(400, '注册失败');
        }
    }
}