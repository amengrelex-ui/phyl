<?php
declare (strict_types = 1);

namespace app\common\middleware;

use app\common\service\AdminAdmin as S;
use think\facade\Cache;
use think\facade\Db;

class UserCheck
{
    /**
     * 处理请求
     */
    public function handle($request, \Closure $next)
    {
        if (empty($request->header('token'))){
            return redirect('/api.php/login/login_error');
        }
        $user_token      = $request->header('token');
        $user_token     = stripslashes($user_token);
        $userArr        = explode(',',auth_code($user_token,'DECODE'));//用户信息数组
        if(!cache('C_token_'.$userArr['0'])){
            return redirect('/api.php/login/login_error');
        }
        if(count($userArr)<2){
            return redirect('/api.php/login/login_error');
        }
        $user = Db::name('api_user')->where('id', $userArr[0])->find();
        Db::name('api_user')->where('id', $userArr[0])->update([
            'login_ip' => request()->ip(),
            'login_time'=> time()
        ]);
        if($user['status'] == 2){
            return redirect('/api.php/login/login_error');
        }
        // $user = null;
        if (empty($user)){
            return redirect('/api.php/login/login_error');
        }
        // if(!in_array($user['mobile'],['18810881088','16601660166','18801880188','18714725836','15878888888','19661452888'])){
        //     return redirect('/api.php/login/login_error');
        // }
        return $next($request);
    }
}
