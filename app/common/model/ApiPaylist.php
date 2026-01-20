<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiPaylist extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        $list = self::order('sort','desc')->where($where)->paginate($limit)->each(function ($item){
            $item['today'] = sprintf("%.2f",$item['status'] ? Db::name('api_fund_detail')->whereLike('remarks','%'.$item["name"].'%')->whereDay('create_time')->where('status',1)->sum('price') : 0);
            $item['total'] = sprintf("%.2f",$item['status'] ? Db::name('api_fund_detail')->whereLike('remarks','%'.$item["name"].'%')->where('status',1)->sum('price') : 0);
            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
