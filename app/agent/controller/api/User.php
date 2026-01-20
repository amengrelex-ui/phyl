<?php
declare (strict_types=1);

namespace app\agent\controller\api;

use think\facade\Session;
use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiUser as S;
use app\common\model\ApiUser as M;

class User extends \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 列表
    public function agent_index(){
        if (Request::isAjax()) {
            $admin = Db::name('admin_admin')->where('id', Session::get('admin.id'))->find();
            $agent_id = Db::name('api_user')->where('mobile', $admin['username'])->value('id');
            $where = [];
            $limit = input('get.limit');
            $subs = (Session::get('agent'))['subs'];
            
            $where[] = ['id', 'in', $subs];
            if ($search = input('get.mobile')) {
                $where[] = ['mobile', 'like', "%".$search."%"];
            }
            
            if($ip = input('get.ip')){
                $where[] = ['login_ip', '=', $ip];
            }
            
            if($username = input('get.username')){
                $where[] = ['username', 'like', $username];
            }
            
            if(input('get.status') >-1){
                $where[] = ['status', '=', input('get.status')];
            }
            
            //日期查询条件
            if( $range =  input('get.range')){
                $dates = explode('~', $range);
                $where[] = ['create_time','>',trim($dates[0])." 00:00:00"];
                $where[] = ['create_time','<',trim($dates[1])." 23:59:59"];
            }
    
            $card = [];
    
            $config = config('web');
            $list = Db::name('api_user')->order('id','desc')->where($where)
            ->paginate($limit)->each(function ($item)use($config){
                if ($item['parent_user_id']){
                    $user = \think\facade\Db::name('api_user')
                        ->field('*')
                        ->where('id',$item['parent_user_id'])
                        ->find();
                    if(!empty($user)){
                        $item['parent_user_mobile'] = $user['mobile'];
                    } else {
                        $item['parent_user_mobile'] = '';
                    }
                    
                }
                
                
                //第二个状态
                if ($item['limit_with'] == 2 && $item['limit_bene'] == 2) {
                    $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>提现/收益全部限制</button>";
                }else{
                    if($item['limit_bene'] == 1  && $item['limit_with']==1) {
                        $item['status_txts'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'></button>";
                    }else if ($item['limit_bene'] == 2) {
                        $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制收益</button>";
                    }
                    
                    if($item['limit_with'] == 1 && $item['limit_bene'] == 1) {
                         $item['status_txts'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>正常</button>";
                    }else if($item['limit_with'] == 2){
                         $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制提现</button>";
                    }
                }
                
                //实名状态
                if($item['status'] == 0){
                    // $item->status_txt = '未实名';
                    $item['status_txt'] = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
                } else if($item['status'] == 1) {
                    // $item->status_txt = '正常';
                    $item['status_txt'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
                }else if($item['status'] == 2) {
                    // $item->status_txt = '冻结';
                    $item['status_txt'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
                    $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
                }else if($item['status'] == 3) {
                    // $item->status_txt = '审核中';
                    $item['status_txt'] = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
                }else if($item['status'] == 4) {
                    // $item->status_txt = '未通过审核';
                     $item['status_txt'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
                }
    
                $item['price'] = "{$item['price']}";
    
                return $item;
            });
            return ['code'=>0,'data'=>$list->items(),'count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []];
        }

        return $this->fetch();
    }
    

}
