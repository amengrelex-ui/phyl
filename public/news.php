<?php
$mysql = mysqli_connect('123.57.245.221'.":".'3306', 'root', '893355320');

// 打开库
mysqli_select_db($mysql, 'cloud_times');
// 设置字符集
mysqli_set_charset($mysql, 'utf8');

$sql = "select * from cloud_times_api_journalism where delete_time IS NULL";