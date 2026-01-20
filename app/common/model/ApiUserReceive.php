<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class ApiUserReceive extends Model
{
    use SoftDelete;
     protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')){
            $where[] = ['u.mobile', '=', $search];
        }
        if ($search = input('get.status')) {
            $where[] = ['r.income_status', '=', $search];
        }
        $list = self::alias('r')->leftJoin('api_user u','u.id = r.user_id')->order('r.income_status','asc')->where($where)->field('u.mobile,r.*')->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}