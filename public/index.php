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
 
error_reporting(E_ALL);   // 报告所有错误
ini_set('display_errors', 1);  // 显示错误信息

require __DIR__ . '/../vendor/autoload.php';
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
header('Access-Control-Allow-Credentials:true');
header('Access-Control-Allow-Methods:GET,POST,OPTIONS');
header('Access-Control-Max-Age:1728000');
header('Content-Type:text/plain charset=UTF-8');
//    header('Content-Length: 0', true);
//    header('status: 204');
//    header('HTTP/1.0 204 No Content');

//定义分隔符
define('DS', DIRECTORY_SEPARATOR);

// 执行HTTP应用并响应
$http = (new App())->http;

// 检测程序安装
if(!is_file(__DIR__ . '/install.lock')){
    $response = $http->name('install')->run();
}

// 域名绑定应用使用统一入口
//$response = $http->run();

// 应用入口
$response = $http->name('index')->run();

$response->send();

$http->end($response);
