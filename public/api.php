<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;
// ini_set('display_errors', 0);  // 禁止错误显示
// error_reporting(0);  // 禁止错误报告

// // 允许所有来源
// header('Access-Control-Allow-Origin: *');
// // 允许的 HTTP 方法
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// // 允许的请求头
// header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// // 如果是 OPTIONS 请求，直接返回 200 响应
// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     header('HTTP/1.1 200 OK');
//     exit;
// }
//if (request()->isOptions()) {
    header('Access-Control-Allow-Origin:*');
    header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
    header('Access-Control-Allow-Credentials:true');
    header('Access-Control-Allow-Methods:GET,POST,OPTIONS');
    // header('Access-Control-Max-Age:1728000');
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit;
    }
  // header('Content-Type:text/plain charset=UTF-8');
    $iptables = [
        '111.23.170.182', '219.133.179.199', '223.88.186.118', '223.88.186.174', '111.23.171.30'
    ];
    if(in_array($_SERVER["REMOTE_ADDR"], $iptables)){
        header("Content-type:text/html;charset=utf-8");
        exit('非法操作');
    }
//     echo json_encode(['code' => 400,'message' => '系统正在维护,请稍等']);
// die;
//    header('Content-Length: 0', true);
//    header('status: 204');
//    header('HTTP/1.0 204 No Content');
//}else{
//    header('Access-Control-Allow-Origin:*');
//    header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
//    header('Access-Control-Allow-Credentials:true');
//    header('Access-Control-Allow-Methods:GET,POST,OPTIONS');
//}
require __DIR__.'/../vendor/autoload.php';

//定义分隔符
define('DS', DIRECTORY_SEPARATOR);

// 执行HTTP应用并响应
$http = (new App())->http;

// 检测程序安装
if (!is_file(__DIR__.'/install.lock')) {
    $response = $http->name('install')->run();
}

// 域名绑定应用使用统一入口
//$response = $http->run();

// 应用入口
$response = $http->name('api')->run();

$response->send();

$http->end($response);
