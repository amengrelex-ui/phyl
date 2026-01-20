<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class AdminAdminTotal extends Model
{
    use SoftDelete;
    protected $deleteTime = "delete_time";
}
