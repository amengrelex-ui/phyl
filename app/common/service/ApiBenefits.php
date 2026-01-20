<?php
declare (strict_types = 1);

namespace app\common\service;

use think\facade\Db;
use think\facade\Request;
use app\common\model\ApiAccount as M;
use app\common\validate\ApiAccount as V;

class ApiBenefits
{
    // 添加
    public static function goAdd($data)
    {
        //验证
        $validate = new V;
        if(!$validate->scene('add')->check($data))
        return ['msg'=>$validate->getError(),'code'=>201];

        $isset = M::where('mobile',$data['mobile'])->find();
        if ($isset){
            return ['msg' => '当前手机号已注册','code'=>201];
        }

        if ((float)$data['price'] < 0.00){
            return ['msg' => '金额错误','code' => 201];
        }

        $data['parent_user_id'] = 0;
        if ($data['parent_user_mobile']){
            if ($data['mobile'] == $data['parent_user_mobile']){
                return ['msg' => '不能指定父级为自身','code'=>201];
            }

            $parent_user = M::where('mobile',$data['parent_user_mobile'])->find()->toArray();
            if (!$parent_user){
                return ['msg'=>'未找到指定父级','code'=>201];
            }
            $data['parent_user_id'] = $parent_user['id'];
        }

        unset($data['parent_user_mobile']);
        $data['password'] = md5(md5($data['password']));

        try {
            M::create($data);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    // 编辑
    public static function goEdit($data,$id)
    {

        $data['id'] = $id;
        //验证
        $validate = new V;
        if(!$validate->scene('edit')->check($data))
        return ['msg'=>$validate->getError(),'code'=>201];


        $account = Db::name('api_account')
            ->where('user_id',$data['user_id'])
            ->where('delete_time IS NULL')
            ->find();
        $account_data = [];
        if ($data['account_name']){
            $account_data['account_name'] = $data['account_name'];
        }

        if ($data['account_number']){
            $account_data['account_number'] = $data['account_number'];
        }

        if ($data['name_of_deposit_bank']){
            $account_data['name_of_deposit_bank'] = $data['name_of_deposit_bank'];
        }
        
        if (!empty($account_data)){
            $account_data['is_default'] = 1;
            $account_data['user_id'] = $data['user_id'];
            if ($account){
                $account_data['update_time'] = date('Y-m-d H:i:s');
                Db::name('api_account')
                    ->where('user_id',$data['user_id'])
                    ->update($account_data);
            }else{
                $account_data['create_time'] = date('Y-m-d H:i:s');
                Db::name('api_account')
                    ->insert($account_data);
            }
        }

        try {
             M::update($data);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 状态
    public static function goStatus($data,$id)
    {
        $model =  M::find($id);
        if ($model->isEmpty())  return ['msg'=>'数据不存在','code'=>201];
        try{
            $model->save([
                'status' => $data,
            ]);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    
    // 状态
    public static function goTransferStatus($data,$id)
    {
        $model =  M::find($id);
        if ($model->isEmpty())  return ['msg'=>'数据不存在','code'=>201];
        try{
            $model->save([
                'transfer_status' => $data,
            ]);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    

    // 删除
    public static function goRemove($id)
    {
        $model = M::find($id);
        if ($model->isEmpty()) return ['msg'=>'数据不存在','code'=>201];
        try{
           $model->delete();
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 批量删除
    public static function goBatchRemove($ids)
    {
        if (!is_array($ids)) return ['msg'=>'数据不存在','code'=>201];
        try{
            M::destroy($ids);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 获取列表
    public static function goRecycle()
    {
        if (Request::isPost()){
            $ids = Request::param('ids');
            if (!is_array($ids)) return ['msg'=>'参数错误','code'=>'201'];
            try{
                if(Request::param('type')){
                    $data = M::onlyTrashed()->whereIn('id', $ids)->select();
                    foreach($data as $k){
                        $k->restore();
                    }
                }else{
                    M::destroy($ids,true);
                }
            }catch (\Exception $e){
                return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
            }
            return ['msg'=>'操作成功'];
        }
        //按用户名
        $where = [];
        $limit = input('get.limit');
        
        $list = M::onlyTrashed()->where($where)->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
