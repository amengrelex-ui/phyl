<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\facade\Request;
use think\facade\Session;
use think\model\concern\SoftDelete;
class ApiUser extends ApiBase
{
    use SoftDelete;
    protected $deleteTime = "delete_time";
    
    protected $globalScope = ['id'];

    public function scopeId($query)
    {
        if($admins = Session::get('admin') ){
            $admin = AdminAdmin::find($admins['id']);
            if($admin['admin_bind_id'] && $admin['id']>1){
                $query->whereIn('id',$admin['subs']);
            }
        }
    }
    // 获取列表
    public static function getList($id=0)
    {
        $where = [];
        $limit = input('get.limit');
        $field = input('get.field','id');
        $order = input('get.order','desc');
        if ($search = input('get.mobile')) {
            $where[] = ['mobile', 'like', "%".$search."%"];
        }
        if($ip = input('get.ip')){
            $where[] = ['login_ip', 'like', "%".$ip."%"];
        }
        
        if($username = input('get.username')){
            $where[] = ['username', '=', $username];
        }
        
        if($id_card = input('get.id_card')){
            $where[] = ['id_card', 'like', "%".$id_card."%"];
        }
        
        if(input('get.status') >-1){
            $where[] = ['status', '=', input('get.status')];
        }

        if(input('get.huimin_apply') >-1){
            $where[] = ['huimin_apply', '=', input('get.huimin_apply')];
        }
        
        //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>',$dates[0]." 00:00:00"];
            $where[] = ['create_time','<',$dates[1]." 23:59:59"];
        }

        $card = [];
        if ($id){
            $total_one = Db::name('api_user')
                ->where('parent_user_id',$id)
                ->column('id');

            $total_tow = [];
            if (!empty($total_one)){
                $total_tow = Db::name('api_user')
                    ->where('parent_user_id', 'in', $total_one)
                    ->column('id');
            }
            $user_id = array_merge($total_one, $total_tow);
            $card = Db::name('api_account')
                ->where('user_id',$id)
                ->where('delete_time IS NULL')
                ->find();
            if (!$user_id){
                return ['code'=>0,'data'=>[],'extend'=>['count' => 0, 'limit' => $limit,'card' => isset($card) ? $card : []]];
            }
            $where[] = ['id','in',$user_id];
        }
        $config = config('web');
        $admin_id = Session::get('admin.id') ??0;

        // $list = self::alias('u')->order($field,$order)->fetchSql(true)->where($where)->select();
        // halt($list);
        $list = self::alias('u')->order($field,$order)->where($where)->paginate($limit)->each(function ($item)use($config,$id,$admin_id){
            if ($item->parent_user_id){
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id',$item->agent_id)
                    ->find();
                if(!empty($user)){
                    $item->parent_user_mobile = $user['mobile'];
                } else {
                    $item->parent_user_mobile = '';
                }
                $total_one = Db::name('api_user')
                ->where('parent_user_id',$item->id)
                ->column('id');

                $total_tow = [];
                if (!empty($total_one)){
                    $total_tow = Db::name('api_user')
                        ->where('parent_user_id', 'in', $total_one)
                        ->column('id');
                }
                if(!$item->level){
                    $item->user_item_level = '普通会员';
                }else{
                    $item->user_item_level = self::getLevelName($item->level);
                }
                $user_id = array_merge($total_one, $total_tow);
                $item->subusers = count($user_id);
                
                $item->parent = Db::name('api_user')->where('id', $item->parent_user_id)->value('mobile');
            }
            
            
            //第二个状态
            if ($item->limit_with == 2 && $item->limit_bene == 2) {
                $item->status_txts = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>提现/收益全部限制</button>";
            }else{
                if($item->limit_bene == 1  && $item->limit_with==1) {
                    $item->status_txts = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'></button>";
                }else if ($item->limit_bene == 2) {
                    $item->status_txts = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制收益</button>";
                }
                
                if($item->limit_with == 1 && $item->limit_bene == 1) {
                     $item->status_txts = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>正常</button>";
                }else if($item->limit_with == 2){
                     $item->status_txts = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制提现</button>";
                }
            }
            $item->admin_id = $admin_id;
            //实名状态
            if($item->status == 0){
                // $item->status_txt = '未实名';
                $item->status_txt = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
            } else if($item->status == 1) {
                // $item->status_txt = '正常';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
            }else if($item->status == 2) {
                // $item->status_txt = '冻结';
                $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
                $item->status_txts = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
            }else if($item->status == 3) {
                // $item->status_txt = '审核中';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
            }else if($item->status == 4) {
                // $item->status_txt = '未通过审核';
                 $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
            }
            $item->price = "{$item->price}";

            if (!$id){
                return $item;
            }

            if ($item->parent_user_id == $id){
                $item->child_level = '直属';
            }else{
                $item->child_level = '二级';
            }
            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }

    public static function getLevelName($level_id){
        return ApiLevel::where('id',$level_id)->value('name');
    }
    
    //获取新增用户
    public static function getNewUserList($id=0){
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $where[] = ['mobile', 'like', "%".$search."%"];
        }
        
        if($ip = input('get.ip')){
            $where[] = ['login_ip', '=', $ip];
        }
        
        if($username = input('get.username')){
            $where[] = ['username', 'like', $username];
        }
        
         //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>',$dates[0]." 00:00:00"];
            $where[] = ['create_time','<',$dates[1]." 23:59:59"];
        }else{
             //新增用户范围每一天的开始和结束
            $day_start = date("Y-m-d H:i:s",strtotime(date('Y-m-d',time())));
            $day_end = date("Y-m-d H:i:s",strtotime($day_start)+86399);
            $where[] = ['create_time','>',$day_start];
            $where[] = ['create_time','<',$day_end];
        }
        

        $card = [];
        if ($id){
            $total_one = Db::name('api_user')
                ->where('parent_user_id',$id)
                ->column('id');

            $total_tow = [];
            if (!empty($total_one)){
                $total_tow = Db::name('api_user')
                    ->where('parent_user_id', 'in', $total_one)
                    ->column('id');
            }

            $user_id = array_merge($total_one, $total_tow);

            $card = Db::name('api_account')
                ->where('user_id',$id)
                ->where('delete_time IS NULL')
                ->find();
            if (!$user_id){
                return ['code'=>0,'data'=>[],'extend'=>['count' => 0, 'limit' => $limit,'card' => isset($card) ? $card : []]];
            }
            $where[] = ['id','in',$user_id];
        }
        // halt(self::order('id','desc')->where($where)->fetchSql(true)->select());
        $config = config('web');
        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item)use($config,$id){
            if ($item->parent_user_id){
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id',$item->agent_id)
                    ->find();
                if(!empty($user)){
                    $item->parent_user_mobile = $user['mobile'];
                } else {
                    $item->parent_user_mobile = '';
                }
                
            }
            
            $all_recharge = Db::name('api_fund_detail')->where('user_id', $item->id)->where('data_type', 2)->where('status', 1)->sum('price');
            if ($all_recharge < 10000) {
                $item->user_item_level = '普通会员';
            } else if($all_recharge >=10000 && $all_recharge < 30000) {
                $item->user_item_level = '白银会员';
            } else if($all_recharge >=30000 && $all_recharge < 50000) {
                $item->user_item_level = '白钻会员';
            } else if($all_recharge >= 50000) {
                $item->user_item_level = '荣誉董事';
            }
            
            
            
            
            
            // if($item->status == 0){
            //     $item->status_txt = '未实名';
            // } else if($item->status == 1) {
            //     $item->status_txt = '正常';
            // }else if($item->status == 2) {
            //     $item->status_txt = '冻结';
            // }else if($item->status == 3) {
            //     $item->status_txt = '审核中';
            // }else if($item->status == 4) {
            //     $item->status_txt = '未通过审核';
            // }
            
            
            if($item->status == 0){
                // $item->status_txt = '未实名';
                $item->status_txt = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
            } else if($item->status == 1) {
                // $item->status_txt = '正常';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
            }else if($item->status == 2) {
                // $item->status_txt = '冻结';
                $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
            }else if($item->status == 3) {
                // $item->status_txt = '审核中';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
            }else if($item->status == 4) {
                // $item->status_txt = '未通过审核';
                 $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
            }

            $item->price = "{$item->price}";

            if (!$id){
                return $item;
            }

            if ($item->parent_user_id == $id){
                $item->child_level = '直属';
            }else{
                $item->child_level = '二级';
            }

            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }
    
    
    //获取实名用户
    public static function getRealnameList($id=0)
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $where[] = ['mobile', 'like', "%".$search."%"];
        }
        
        if($ip = input('get.ip')){
            $where[] = ['login_ip', '=', $ip];
        }
        
        if($username = input('get.username')){
            $where[] = ['username', 'like', $username];
        }
        
        //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>',$dates[0]." 00:00:00"];
            $where[] = ['create_time','<',$dates[1]." 23:59:59"];
        }
        
        
        if(input('get.status') >-1){
            $where[] = ['status', '=', input('get.status')];
        } else {
            // $where[] = ['status', 'in', [1,3,4]];
            $where[] = ['status', 'in', [1,3]];
        }
        $card = [];
        if ($id){
            $total_one = Db::name('api_user')
                ->where('parent_user_id',$id)
                ->column('id');
            $total_tow = [];
            if (!empty($total_one)){
                $total_tow = Db::name('api_user')
                    ->where('parent_user_id', 'in', $total_one)
                    ->column('id');
            }

            $user_id = array_merge($total_one, $total_tow);

            $card = Db::name('api_account')
                ->where('user_id',$id)
                ->where('delete_time IS NULL')
                ->find();
            if (!$user_id){
                return ['code'=>0,'data'=>[],'extend'=>['count' => 0, 'limit' => $limit,'card' => isset($card) ? $card : []]];
            }
            $where[] = ['id','in',$user_id];
        }

        $config = config('web');
        
        
        $list = self::order('status','desc')->where($where)
        ->paginate($limit)->each(function ($item)use($config,$id){
            
            if ($item->parent_user_id){
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id',$item->agent_id)
                    ->find();
                if(!empty($user)){
                    $item->parent_user_mobile = $user['mobile'];
                } else {
                    $item->parent_user_mobile = '';
                }
                
            }
            $all_recharge = Db::name('api_fund_detail')->where('user_id', $item->id)->where('data_type', 2)->where('status', 1)->sum('price');
            if ($all_recharge < 10000) {
                $item->user_item_level = '普通会员';
            } else if($all_recharge >=10000 && $all_recharge < 30000) {
                $item->user_item_level = '白银会员';
            } else if($all_recharge >=30000 && $all_recharge < 50000) {
                $item->user_item_level = '白钻会员';
            } else if($all_recharge >= 50000) {
                $item->user_item_level = '荣誉董事';
            }
            
            // if($item->status == 0){
            //     $item->status_txt = '未实名';
            // } else if($item->status == 1) {
            //     $item->status_txt = '正常';
            // }else if($item->status == 2) {
            //     $item->status_txt = '冻结';
            // }else if($item->status == 3) {
            //     $item->status_txt = '审核中';
            // }else if($item->status == 4) {
            //     $item->status_txt = '未通过审核';
            // }
            
            
            if($item->status == 0){
                // $item->status_txt = '未实名';
                $item->status_txt = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
            } else if($item->status == 1) {
                // $item->status_txt = '正常';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
            }else if($item->status == 2) {
                // $item->status_txt = '冻结';
                $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
            }else if($item->status == 3) {
                // $item->status_txt = '审核中';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
            }else if($item->status == 4) {
                // $item->status_txt = '未通过审核';
                 $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
            }

            $item->price = "{$item->price}";

            if (!$id){
                return $item;
            }

            if ($item->parent_user_id == $id){
                $item->child_level = '直属';
            }else{
                $item->child_level = '二级';
            }

            return $item;
        });
        
       
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }

    //获取实名用户
    public static function getApplyWallet($id=0)
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $where[] = ['mobile', 'like', "%".$search."%"];
        }
        
        if($ip = input('get.ip')){
            $where[] = ['login_ip', '=', $ip];
        }

        if($huimin_apply = input('get.huimin_apply')){
            $where[] = ['huimin_apply', '=', $huimin_apply];
        }else{
            $where[] = ['huimin_apply', 'in',[1,2]];
        }
        
        if($username = input('get.username')){
            $where[] = ['username', 'like', $username];
        }
        
        //日期查询条件
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['create_time','>',$dates[0]." 00:00:00"];
            $where[] = ['create_time','<',$dates[1]." 23:59:59"];
        }
        
        
        if(input('get.status') >-1){
            $where[] = ['status', '=', input('get.status')];
        } else {
            // $where[] = ['status', 'in', [1,3,4]];
            $where[] = ['status', 'in', [1,3]];
        }

        $card = [];
        if ($id){
            $total_one = Db::name('api_user')
                ->where('parent_user_id',$id)
                ->column('id');
            $total_tow = [];
            if (!empty($total_one)){
                $total_tow = Db::name('api_user')
                    ->where('parent_user_id', 'in', $total_one)
                    ->column('id');
            }

            $user_id = array_merge($total_one, $total_tow);

            $card = Db::name('api_account')
                ->where('user_id',$id)
                ->where('delete_time IS NULL')
                ->find();
            if (!$user_id){
                return ['code'=>0,'data'=>[],'extend'=>['count' => 0, 'limit' => $limit,'card' => isset($card) ? $card : []]];
            }
            $where[] = ['id','in',$user_id];
        }

        $config = config('web');
        
        
        $list = self::order('apply_time','desc')->where($where)
        ->paginate($limit)->each(function ($item)use($config,$id){
            
            if ($item->parent_user_id){
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id',$item->agent_id)
                    ->find();
                if(!empty($user)){
                    $item->parent_user_mobile = $user['mobile'];
                } else {
                    $item->parent_user_mobile = '';
                }
                
            }
            
            
            if($item->status == 0){
                // $item->status_txt = '未实名';
                $item->status_txt = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
            } else if($item->status == 1) {
                // $item->status_txt = '正常';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
            }else if($item->status == 2) {
                // $item->status_txt = '冻结';
                $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
            }else if($item->status == 3) {
                // $item->status_txt = '审核中';
                $item->status_txt = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
            }else if($item->status == 4) {
                // $item->status_txt = '未通过审核';
                 $item->status_txt = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
            }

            $item->price = "{$item->price}";

            if (!$id){
                return $item;
            }

            if ($item->parent_user_id == $id){
                $item->child_level = '直属';
            }else{
                $item->child_level = '二级';
            }

            return $item;
        });
        
       
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }


    public static function get_level_ids($user_ids = [],$index=0,$teamIds = [], $level = 0, $result = [])
    {
        $ids = Db::name('api_user')->whereIn('parent_user_id', $user_ids)->column('id');
        if ($level < 3){
            if($ids){
                if($index == $level){
                    $result = $ids;
                    return $result;
                }
                $level++;
                return self::get_level_ids($ids,$index,$teamIds, $level, $result);
            }
        }
        return $result;
    }

    public static function get_level_allIds($user_ids = [],$teamIds = [], $level = 0)
    {
        $ids = Db::name('api_user')->whereIn('parent_user_id', $user_ids)->column('id');
        if ($level < 3){
            if($ids){
                $level++;
                $teamIds = array_merge($ids,$teamIds);
                return self::get_level_allIds($ids,$teamIds, $level);
            }
        }
        return $teamIds;
    }
    
    
}
