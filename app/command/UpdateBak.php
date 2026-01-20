<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use app\common\service\AdminAdmin;

class UpdateBak extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('update')
        ->setDescription('定时计划：更新下级用户');
    }

    protected function execute(Input $input, Output $output)
    {
        $admins = Db::name('admin_admin')->where('admin_bind_id', '>',0)->field('id,admin_bind_id,subtotal')->order('group asc')->select();
        if (count($admins)) {
            Db::startTrans();
            try {
                foreach ($admins as $admin){
                    $subs = array_unique(self::getAllSubUserIds($admin['admin_bind_id']));
                    if (count($subs) > $admin['subtotal']){
                        $substr =  implode(',', $subs);
                        Db::name('admin_admin')->where('id', $admin['id'])->update([
                            'subs' => $substr,
                            'subtotal' => count($subs)
                        ]);
                    }
                }
                Db::commit();
                $output->writeln('更新成功');
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln('更新失败');
            }
        }
    }

    public static function getAllSubUserIds($userId, &$subs = [], &$processed = [])
    {
        if (in_array($userId, $processed)){
            return $subs;
        }
        $processed[] = $userId;
        $users = Db::name('api_user')->where('parent_user_id', $userId)->select();
        foreach ($users as $user) {
            $subs[] = $user['id'];
            self::getAllSubUserIds($user['id'], $subs, $processed);
        }
        return $subs;
    }
}
