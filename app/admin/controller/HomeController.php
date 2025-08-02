<?php

namespace app\admin\controller;

use app\model\Capital;
use app\model\Order;
use app\model\Order4;
use app\model\Order5;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserProduct;
use app\model\UserSignin;
use app\model\YuanmengUser;

class HomeController extends AuthController
{
    public function index()
    {
        if(!session('is_admin')){
            $this->assign('data', []);
            return $this->fetch();
        }

        $today = date("Y-m-d 00:00:00", time());
        $yesterday_start = date('Y-m-d 00:00:00',strtotime("-1 day"));
        $yesterday_end = date('Y-m-d 23:59:59',strtotime("-1 day"));

        $data = $arr = [];

        $arr['title3'] = '今天新增注册量';
        $arr['value3'] = User::where('created_at', '>=', $today)->count();
//        $arr['title2'] = '昨日新增注册量';
//        $arr['value2'] = User::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count();
        $arr['title1'] = '总注册量';
        $arr['value1'] = User::count();
//        $arr['title2'] = '复盘前注册量';
//        $arr['value2'] = User::where('created_at', '<=', '2024-10-24')->count();
//        $arr['title1'] = '复盘注册量';
//        $arr['value1'] = User::where('created_at', '>', '2024-10-24')->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title3'] = '今日激活会员数量';
//        $arr['value3'] = User::where('is_active', 1)->where('created_at', '>=', $today)->count();
        $arr['value3'] = User::where('is_active', 1)->where('active_time', '>=', strtotime($today))->count();
//        $arr['title2'] = '昨日激活会员数量';
//        $arr['value2'] = User::where('is_active', 1)->where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count();
        $arr['title1'] = '总激活会员数量';
        $arr['value1'] = User::where('is_active', 1)->count();
//        $arr['title2'] = '复盘前激活会员数量';
//        $arr['value2'] = User::where('is_active', 1)->where('created_at', '<=', '2024-10-24')->count();
//        $arr['title1'] = '复盘激活会员数量';
//        $arr['value1'] = User::where('is_active', 1)->where('created_at', '>', '2024-10-24')->count();
        $arr['url'] = '';
        $data[] = $arr;


        $arr['title3'] = '今日充值总金额';
        $arr['value3'] = round(Capital::where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
//        $arr['title2'] = '昨日充值总金额';
//        $arr['value2'] = round(Capital::where('status', 2)->where('type', 1)->where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->sum('amount'), 2);
        $arr['title1'] = '总充值金额';
        $arr['value1'] = round(Capital::where('status', 2)->where('type', 1)->sum('amount'), 2);
//        $arr['title2'] = '复盘前充值金额';
//        $arr['value2'] = round(Capital::where('status', 2)->where('type', 1)->where('created_at', '<=', '2024-10-24')->sum('amount'), 2);
//        $arr['title1'] = '复盘充值金额';
//        $arr['value1'] = round(Capital::where('status', 2)->where('type', 1)->where('created_at', '>', '2024-10-24')->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title3'] = '今日提现总金额';
        $arr['value3'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $today)->sum('amount'), 2);
//        $arr['title2'] = '昨日提现总金额';
//        $arr['value2'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->sum('amount'), 2);
        $arr['title1'] = '总提现金额';
        $arr['value1'] = round(0 - Capital::where('status', 2)->where('type', 2)->sum('amount'), 2);
//        $arr['title2'] = '复盘前提现总金额';
//        $arr['value2'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '<=', '2024-10-24')->sum('amount'), 2);
//        $arr['title1'] = '复盘提现总金额';
//        $arr['value1'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '>', '2024-10-24')->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title3'] = '今日充值总次数';
        $arr['value3'] = Capital::where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->count();
//        $arr['title2'] = '昨日充值总次数';
//        $arr['value2'] = Capital::where('status', 2)->where('type', 1)->where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count();
        $arr['title1'] = '充值总次数';
        $arr['value1'] = Capital::where('status', 2)->where('type', 1)->count();
//        $arr['title2'] = '复盘前充值总次数';
//        $arr['value2'] = Capital::where('status', 2)->where('type', 1)->where('created_at', '<=', '2024-10-24')->count();
//        $arr['title1'] = '复盘充值总次数';
//        $arr['value1'] = Capital::where('status', 2)->where('type', 1)->where('created_at', '>', '2024-10-24')->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title3'] = '今日提现总次数';
        $arr['value3'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $today)->count();
//        $arr['title2'] = '昨日提现总次数';
//        $arr['value2'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count();
        $arr['title1'] = '提现总次数';
        $arr['value1'] = Capital::where('status', 2)->where('type', 2)->count();
//        $arr['title2'] = '复盘前提现总次数';
//        $arr['value2'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '<=', '2024-10-24')->count();
//        $arr['title1'] = '复盘提现总次数';
//        $arr['value1'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '>', '2024-10-24')->count();
        $arr['url'] = '';
        $data[] = $arr;

//        $arr['title3'] = '今日申领人数';
//        $arr['value3'] = round(Order::where('created_at', '>=', $today)->count('distinct user_id') + Order4::where('created_at', '>=', $today)->count('distinct user_id') + Order5::where('created_at', '>=', $today)->count('distinct user_id'), 2);
//        $arr['title2'] = '昨日申领人数';
//        $arr['value2'] = round(Order::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id') + Order4::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id') +  Order5::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id'), 2);
//        $arr['title1'] = '总申领人数';
//        $arr['value1'] = round((UserProduct::count('distinct user_id')+ Order::count('distinct user_id') + Order4::count('distinct user_id')), 2);
//        $arr['title2'] = '复盘前申领人数';
//        $arr['value2'] = round(UserProduct::count('distinct user_id'), 2);
//        $arr['title1'] = '复盘申领人数';
//        $arr['value1'] = round(Order::count('distinct user_id')+Order4::count('distinct user_id'), 2);

        $arr['title3'] = '今日投资金额';
        $kaihujine =  UserBalanceLog::where('type',26)->where('created_at','>=', $today)->sum('change_balance');
        $kaihuZong =  UserBalanceLog::where('type',26)->sum('change_balance');
        $arr['value3'] =  (round(Order::where('created_at', '>=', $today)->sum('price'), 2))+$kaihujine;
//        $arr['title2'] = '昨日申领人数';
//        $arr['value2'] = round(Order::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id') + Order4::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id') +  Order5::where('created_at', '>=', $yesterday_start)->where('created_at', '<=', $yesterday_end)->count('distinct user_id'), 2);
        $arr['title1'] = '投资总金额';
        $arr['value1'] =  (round(Order::sum('price'), 2))+$kaihuZong;

        $arr['url'] = '';
        $data[] = $arr;

        $todayStart = date("Y-m-d 00:00:00");
        $now = date("Y-m-d 23:59:59");

        $arr['title3'] = '今日会员登录数';
        $arr['value3'] = User::where('last_login_time', '>=', $todayStart)
            ->where('last_login_time', '<=', $now)
            ->count();
        $arr['title1'] = '会员总登录数';
        $arr['value1'] =  User::count();

        $arr['url'] = '';
        $data[] = $arr;

        $signin_date = date('Y-m-d');
        $arr['title3'] = '今日会议签到记录数量';
        $arr['value3'] = UserBalanceLog::where('type',58)->whereBetweenTime('created_at', date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'))->count();
        $arr['title2'] = '签到记录数量';
        $arr['value2'] = UserSignin::count();
        $arr['title1'] = '今日签到记录数';
        $arr['value1'] = UserSignin::where('signin_date', $signin_date)->count();
        $arr['url'] = '';
        $data[] = $arr;


        $this->assign('data', $data);

        return $this->fetch();
    }

    public function uploadSummernoteImg()
    {
        $img_url = upload_file2('img_url',true,false);

        return out(['img_url' => env('app.img_host').$img_url, 'filename' => md5(time()).'.jpg']);
    }
}