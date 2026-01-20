<?php

namespace app\api\controller;

use think\facade\Request;
use app\common\util\Upload as Up;

class File extends \app\AdminBaseController
{
    public function upload()
    {
        // 获取表单上传文件 例如上传了001.jpg
        // $file = $request->file('imgFile');
        // if (!$file){
        //     return returnJson(400,'失败');
        // }
        
        // 移动到框架应用根目录/uploads/ 目录下
        $ret = Up::putFile(Request::file(),Request::post('imgFile'));
        if ($ret) {
            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            return returnJson(200, '成功', ['file_path' => "https://".$_SERVER['HTTP_HOST'].$ret['data']['src'],'file_thumb' => "https://".$_SERVER['HTTP_HOST'].$ret['data']['thumb']]);
        } else {
            // 上传失败获取错误信息
            return returnJson(400, '失败' . $file->getError());
        }
    }
}