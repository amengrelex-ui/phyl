<?php

declare(strict_types=1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;

class ApiProduct extends Model
{
    use SoftDelete;
    protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');

        //按产品名称查找
        if ($name = input("name")) {
            $where[] = ["name", "like", "%" . $name . "%"];
        }
        $list = self::order('id', 'desc')
            ->where($where)
            ->paginate($limit)
            ->each(
                function ($item) {
                    if ($item['product_type'] == 0) {
                        $item->product_type_convert = '兑换券';
                    } else if ($item['product_type'] == 1) {
                        $item->product_type_convert = '养老补助';
                    } else if ($item['product_type'] == 2) {
                        $item->product_type_convert = '消费补贴';
                    } else if ($item['product_type'] == 3) {
                        $item->product_type_convert = '居民保险';
                    }
                    if ($item['method'] == 1) {
                        $item['method_txt'] = '每日返利到期还本';
                    } else if ($item['method'] == 2) {
                        $item['method_txt'] = '到期返利返本的选择';
                    }
                    if (!$item['voucher_id']) {
                        $item['voucher_txt'] = "无";
                    } else {
                        $item['voucher_txt'] = Db::name('api_voucher')->where('id', $item['voucher_id'])->value('name');
                    }
                    $item->proceeds = $item->proceeds;
                    return $item;
                }
            );
        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
        // return ['code'=>0,'data'=>$list->items(),'count' => $list->total(), 'limit' => $limit];
    }

    public static function get_active_ids()
    {
        return self::where('sum_type', 1)->column('id');
    }
}
