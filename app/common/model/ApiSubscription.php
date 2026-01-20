<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiSubscription extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList($data = [])
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

        if ($search = input('get.product_type')) {
            $where[] = ['product_type','in', $search];
        }

        if ($search = input('get.status')) {
            if ($search != 3){
                $where[] = ['status','in', $search];
            }
        }

        if ($search = input('get.name')) {
            $where[] = ['name','like', "%{$search}%"];
        }

        $list = self::order('id','desc')->where($where);

        if (input('get.status') == 3){
            $list = $list->onlyTrashed();
        }

        $list = $list->paginate($limit)->each(function ($item){
            $user = \think\facade\Db::name('api_user')
                ->field('*')
                ->where('id',$item->user_id)
                ->find();
            $item->mobile = $user['mobile'];

            switch ($item->product_type){
                case 1:
                    $item->product_type = '稳健产品';
                    break;
                case 2:
                    $item->product_type = '收益产品';
                    break;
            }

            if ($item->delete_time){
                $item->status = '未支付';
            }else{
                switch ($item->status){
                    case 1:
                        $item->status = '认购中';
                        break;
                    case 2:
                        $item->status = '已完成';
                        break;
                }
            }

            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
