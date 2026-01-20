<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiFundDetail extends Model
{
    use SoftDelete;
    protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList($id = 0)
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $user_id = Db::name('api_user')
                ->where('mobile', 'like', "%".$search."%")
                ->column('id');
            if (empty($user_id)) {
                return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
            }
            $where[] = ['user_id', 'in', $user_id];
        }

        if (!empty($id)){
            $where[] = ['user_id','in',$id];
        }

        if ($search = input('get.data_type')) {
            $where[] = ['data_type','in', $search];
        }

        if ($search = input('get.status')) {
            $where[] = ['status','in', $search];
        }else{
            $where[] = ['status','<>',3];
        }

        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item){
            $user = \think\facade\Db::name('api_user')
                ->field('*')
                ->where('id',$item->user_id)
                ->find();
            $item->mobile = !empty($user['mobile']) ? $user['mobile'] : 0;
            
            return $item;
        });
       
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
    
    //获取充值列表
    public static function getRechargeList($id = 0){
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $user_id = Db::name('api_user')
                ->where('mobile', 'like', "%".$search."%")
                ->column('id');
            if (empty($user_id)) {
                return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
            }
            $where[] = ['user_id', 'in', $user_id];
        }

        if (!empty($id)){
            $where[] = ['user_id','in',$id];
        }

        $where[] = ['data_type','in', 2];
            
        if ($search = input('get.status')) {
            $where[] = ['status','in', $search];
        }
        
        
        //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>', trim($dates[0])." 00:00:00"];
            $where[] = ['create_time','<', trim($dates[1])." 23:59:59"]; // 没有空格？
        }
        

        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item){
            $user = \think\facade\Db::name('api_user')
                ->field('*')
                ->where('id',$item->user_id)
                ->find();
            $item->mobile = !empty($user['mobile']) ? $user['mobile'] : 0;
            $recharge_type_txt = '充值';
            if($item->recharge_type == 3){
                $recharge_type_txt = '积分兑换';
            } else if($item->recharge_type == 2){
                $recharge_type_txt = '任务返现';
            }
            $item->username = $user['username'];
            $parent_mobile = Db::name('api_user')->where('id', $user['agent_id'])->value('mobile');
            $item->parent_mobile = $parent_mobile ? $parent_mobile : "";
            $item->recharge_type_txt = $recharge_type_txt;
            return $item;
        });
       
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    } 
    
    
    
    //获取提现列表
    public static function getWithdrawList($id = 0){
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $user_id = Db::name('api_user')
                ->where('mobile', 'like', "%".$search."%")
                ->column('id');
            if (empty($user_id)) {
                return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
            }
            $where[] = ['user_id', 'in', $user_id];
        }
        
        
         //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>', trim($dates[0])." 00:00:00"];
            $where[] = ['create_time','<', trim($dates[1])." 23:59:59"];
        }
        

        if (!empty($id)){
            $where[] = ['user_id','in',$id];
        }

        $where[] = ['data_type','in', 1];
            
        if ($search = input('get.status')) {
            $where[] = ['status','in', $search];
        }

        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item){
            $user = \think\facade\Db::name('api_user')
                // ->field('a.*,p.account_name,p.account_number,p.name_of_deposit_bank')
                // ->join('api_account p','a.id = p.user_id')
                ->where('id',$item->user_id)
                ->find();
            $account = \think\facade\Db::name('api_account')
                ->where('user_id', $item->user_id)
                ->where('type',$item['type'])
                ->where('delete_time IS NULL')
                ->find();
            $item->username = $user['username'];
            $item->mobile = !empty($user['mobile']) ? $user['mobile'] : 0;
            if($item['type']){
                $item->account_name = $account['account_name'];
                $item->account_number = $account['account_number'];
                $item->name_of_deposit_bank = '-';
            }else{
                $item->account_name = $account['account_name'];
                $item->account_number = $account['account_number'];
                $item->name_of_deposit_bank = $account['name_of_deposit_bank'];
            }
            $parent_mobile = Db::name('api_user')->where('id', $user['agent_id'])->value('mobile');
            $item->parent_mobile = $parent_mobile ? $parent_mobile : "";
            // $item->account_number = $item->account_number;
            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    } 
}
