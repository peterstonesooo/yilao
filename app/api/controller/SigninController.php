<?php

namespace app\api\controller;

use app\model\HongbaoSigninPrize;
use app\model\HongbaoSigninPrizeLog;
use app\model\HongbaoUserSetting;
use app\model\MeetingRecords;
use app\model\Order5;
use app\model\TurntableSignPrize;
use app\model\TurntableInvitePrize;
use app\model\TurntableInvite5Prize;
use app\model\UserBalanceLog;
use think\db\Query;
use think\facade\Cache;
use app\model\User;
use app\model\TurntableUserLog;
use app\model\UserSignin;
use Exception;
use think\facade\Db;

class SigninController extends AuthController
{

    public function userSignin()
    {
        $user = $this->user;
        $signin_date = date('Y-m-d');
        $last_date = date('Y-m-d', strtotime("-1 days"));
        $user = User::where('id', $user['id'])->find();

        if($user['is_active'] == 0){
            // return out(null, 10001, '请先激活账号');
        }

        Db::startTrans();
        try {
            if (UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
                return out(null, 10001, '您今天已经签到');
            }
            //7天签到奖励
            $signin_amount = ['1'=>8,'2'=>18,'3'=>28,'4'=>38,'5'=>48,'6'=>58,'7'=>68];
            $last_sign = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
            // if(!$last_sign) {
            //     $signin = UserSignin::create([
            //         'user_id' => $user['id'],
            //         'signin_date' => $signin_date,
            //     ]);
            // } else {
            //判断是否连续签到
            if ($last_sign && $last_sign['signin_date'] == $last_date) {
                $continue_days = $last_sign['continue_days'] + 1;
//                    if($continue_days % 30 == 0) {
//                        User::changeInc($user['id'],100000,'yixiaoduizijin',100,$last_sign['id'],7,'连续签到30天奖励');
//                    }
                // if($continue_days > 7) {
                //     $amount = $continue_days % 7;
                // } else {
                //     $amount = $continue_days;
                // }
                // if($amount == 0) {
                //     $amount = 7;
                // }
                //七天签到完成也是重新开始。金额到福利钱包。
                if($last_sign['continue_days']==7){
                    $continue_days = 1;
                }
                if($continue_days % 7 == 0) {
                    //加一次抽奖机会
                    $ret = User::where('id', $user['id'])->inc('huodong', 1)->update();
                }

            } else {
                $continue_days = 1;

            }

            //User::changeInc($user['id'], $amount, 'integral', 17 ,0 , 2);
            $signin = UserSignin::create([
                'user_id' => $user['id'],
                'signin_date' => $signin_date,
                'continue_days' => $continue_days
            ]);

            User::changeInc($user['id'],$signin_amount[$continue_days],'balance',17,$signin['id'],7);
            //  }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(null, 200, '成功领取签到奖励'.$signin_amount[$continue_days].'元');

    }

    //开红包
    public function kaiHongBao()
    {
        $user = $this->user;

        //判断今天是否签到
        $signin_date = date('Y-m-d');
        $todaySigninCount = UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count();
        if ($todaySigninCount == 0) {
            return out(null, 10001, '您还没签到');
        }

        //判断今天是否开过红包
        $prizeCount = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('signin_date',$signin_date)->count();
        if ($prizeCount){
            //return out(null, 10001, '已开启红包，请明日再来');
        }

        //集字赢大礼 start
        //签到（lianxu qiandao ）
        $last_sign = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
        //内定
        $luckyCount = HongbaoUserSetting::where('user_id',$user['id'])->where('status',0)->count();
        if ($luckyCount >= 1){
            $prizeData = $this->luckyUser($user['id'], $user['created_at'], $last_sign['id'], $last_sign['continue_days'], $user['phone']);
        } else {
            $prizeData = $this->jiziyingdali($user['id'], $user['created_at'], $last_sign['id'], $last_sign['continue_days'] ,$user['phone']);
        }
        //集字赢大礼 end

        return out($prizeData);
    }

    //集字赢大礼
    // $userId 用户ID
    // $userCreatedAt 用户创建时间
    // $signId  签到ID
    // $continueDays 连续签到次数
    public function jiziyingdali($userId, $userCreatedAt, $signId, $continueDays, $phone)
    {
        //大于这个时间为新人  小于这个时间是老人
        $signin_date = date('Y-m-d');
        $ymdhis = '2025-01-06 00:00:00';
        //缴纳金判断
        $count = Order5::where('user_id',$userId)->count();

        //新用户完成开具资金来源证明，新用户必得100元蛇年红包
        if (strtotime($userCreatedAt) >= strtotime($ymdhis) && $count == 1){
            $money = "100";
            //第一次签到判断
            $newFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',13)->where('type',106)->count();
            if ($newFirstCount == 0){
                User::changeInc($userId,100,'shenianhongbao',106, $signId,14,'蛇年红包');
                return (string)$money;
            }
            //开具资金来源证明不管新老用户连续签到十五天必得蛇年福运金100元
            if ($continueDays % 15 == 0){
                User::changeInc($userId,100,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }
        }

        //未完成开具资金来源证明老用户签到只会中字 连续签到3天中20元蛇年红包 连续签到5天中10元蛇年红包
        if (strtotime($userCreatedAt) < strtotime($ymdhis) && $count == 0){
            if ($continueDays % 3 == 0){
                $money = mt_rand(1,5);
                User::changeInc($userId,$money,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }

            if ($continueDays % 10 == 0){
                $money = mt_rand(5,10);
                User::changeInc($userId,$money,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }
        }

        //老用户开具完成资金来源证明第一次签到即可中50元，连续签到7天中20元，连续签到10天中20元，连续签到15天中20元
        if (strtotime($userCreatedAt) < strtotime($ymdhis) && $count == 1){
            //第一次签到判断
            $oldFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',14)->where('type',107)->count();
            if ($oldFirstCount == 0){
                User::changeInc($userId,50,'shenianhongbao',107, $signId,14,'蛇年红包');
                return 50;
            }
            if ($continueDays % 7 == 0){
                $qian7 = mt_rand(1,5);
                User::changeInc($userId,$qian7,'shenianhongbao',107, $signId,14,'蛇年红包');
                return (string)$qian7;
            }

            if ($continueDays % 10 == 0){
                $qian10 = mt_rand(5,10);
                User::changeInc($userId,$qian10,'shenianhongbao',107, $signId,14,'蛇年红包');
                return (string)$qian10;
            }

            if ($continueDays % 15 == 0){
                $qian15 = mt_rand(10,15);
                User::changeInc($userId,$qian15,'shenianhongbao',107, $signId,14,'蛇年红包');
                //开具资金来源证明不管新老用户连续签到十五天必得蛇年福运金100元
                User::changeInc($userId,100,'shenianhongbao',309, $signId,14,'蛇年红包');
                return (string)$qian15;
            }
        }

        //新用户未完成开具资金证明签到首次必中20元蛇年福运金，未完成一直不满100元
        if (strtotime($userCreatedAt) >= strtotime($ymdhis) && $count == 0){
            //第一次签到判断
            $oldFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',14)->where('type',108)->count();
            if ($oldFirstCount == 0){
                User::changeInc($userId,20,'shenianhongbao',108, $signId,14,'蛇年红包');
                return "20";
            }
        }

        //随机奖品
        $result = array();
        $proArr = HongbaoSigninPrize::order('id', 'asc')->select()->toArray();
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val['v'];
        }
        $proSum = array_sum($arr);
        asort($arr);
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $proArr[$k];
                break;
            } else {
                $proSum -= $v;
            }
        }

        //mp_hongbao_signin_prize_log
        HongbaoSigninPrizeLog::create([
            'user_id' => $userId,
            'prize_id' => $result['id'],
            'phone' => $phone,
            'prize_name' => $result['name'],
            'signin_date' => $signin_date,
            'signin_days' => $continueDays,
        ]);

        return $result['name'];
    }

    //内定人
    public function luckyUser($userId, $userCreatedAt, $signId, $continueDays, $phone)
    {
        $data = HongbaoUserSetting::where('user_id',$userId)->where('status',0)->order('id asc')->limit(1)->find();
        HongbaoSigninPrizeLog::create([
            'user_id' => $userId,
            'prize_id' => $data['prize_id'],
            'phone' => $phone,
            'prize_name' => $data['prize_name'],
            'signin_date' => date('Y-m-d'),
            'signin_days' => $continueDays,
        ]);

        HongbaoUserSetting::where('id',$data['id'])->update(['status' => 1]);
        return $data['name'];
    }




    public function signinRecord()
    {
        $user = $this->user;
        $month = self::getMonth();
        $data = [];

        $list = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->select()->toArray();
        $day = array_column($list, 'signin_date');
        foreach ($month as $key => $value) {
            if(in_array($value, $day)){
                $data[] = [
                    'date' => $value,
                    'type' => 'normal'
                ];
            } else {
                $today = date('Y-m-d');
                if($value <= $today) {
                    $data[] = [
                        'date' => $value,
                        'type' => 'abnormal'
                    ];
                }
            }

        }
        $one = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
        return out([
            'total_continue_days' => $one['continue_days'] ?? 0,
            'list' => $data
        ]);
    }

    public static function getMonth($time = '', $format='Y-m-d'){
        $time = $time != '' ? $time : time();
        //获取当前周几
        $week = date('d', $time);
        $date = [];
        for ($i=1; $i<= date('t', $time); $i++){
            $date[$i] = date($format ,strtotime( '+' . ($i-$week) .' days', $time));
        }
        return $date;
    }

    public function signinPrizeList()
    {
        $prizeList = TurntableSignPrize::order('id', 'asc')->select();
        return out($prizeList);
    }

    /**
     * 1 签到幸运大转盘（66,88,99,128,188数字人民币轮序）
     */
    public function turntableSign()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-sign-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $signin_date = date('Y-m-d');
        //是否签到
        $isSign = UserSignin::where('signin_date', $signin_date)->where('user_id', $user->id)->find();
        if ($isSign) {
            return out(null, 10001, '今日已签到');
        }

        //确定奖励
        $lastPrize = UserSignin::where('user_id', $user->id)->where('prize_id', '>', 0)->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableSignPrize::order('id', 'asc')->find();
        } else {
            $prize = TurntableSignPrize::where('id', '>', $lastPrize['prize_id'])->order('id', 'asc')->find();
            if (empty($prize)) {
                $prize = TurntableSignPrize::order('id', 'asc')->find();
            }
        }
        
        $sigiinId = UserSignin::insertGetId([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'reward' => $prize['name'],
            'signin_date' => $signin_date,
        ]);

        User::changeInc($user->id,$prize['name'],'digital_yuan_amount',34,$sigiinId,3, '签到数字人民币奖励','',1,'SR');

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    /**
     * 签到、抽奖记录
     */
    // public function signinRecord()
    // {
    //     $user = $this->user;
    //     $time = date("Y-m")."-01";

    //     $list = UserSignin::where('user_id', $user['id'])->where("signin_date",'>=',$time)->order('id', 'desc')->select()->toArray();
    //     foreach ($list as &$item) {
    //         $item['day'] = date('d', strtotime($item['signin_date']));
    //     }
    //     return out([
    //         'total_signin_num' => count($list),
    //         'list' => $list
    //     ]);
    // }

    /**
     * 推荐幸运大转盘
     */
    public function turntableInvite()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-invite-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        //是否有抽奖机会
        $isSign = 0;
        if ($isSign <= 0) {
            return out(null, 10001, '无转盘机会');
        }

        //确定奖励
        $lastPrize = TurntableUserLog::where('user_id', $user->id)->where('type', 'invite')->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableInvitePrize::find(1);
        } else {
            $prize = TurntableInvitePrize::find($lastPrize['prize_id'] + 1);
            if (empty($prize)) {
                $prize = TurntableInvitePrize::find(1);
            }
        }
        
        TurntableUserLog::insert([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'invite',
        ]);

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    /**
     * 推荐五人幸运大转盘
     */
    public function turntableInvite5()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-ivnite5-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        //是否有抽奖机会
        $isSign = 0;
        if ($isSign <= 0) {
            return out(null, 10001, '无转盘机会');
        }

        //确定奖励
        $lastPrize = TurntableUserLog::where('user_id', $user->id)->where('type', 'invite5')->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableInvite5Prize::find(1);
        } else {
            $prize = TurntableInvite5Prize::find($lastPrize['prize_id'] + 1);
            if (empty($prize)) {
                $prize = TurntableInvite5Prize::find(1);
            }
        }
        
        TurntableUserLog::insert([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'invite5',
        ]);

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    //抽奖
    // public function getRand($proArr) { 
    //     $result = ''; 
    //     $proSum = array_sum($proArr);  
    //     foreach ($proArr as $key => $proCur) { 
    //         $randNum = mt_rand(1, $proSum); 
    //         if ($randNum <= $proCur) { 
    //             $result = $key; 
    //             break; 
    //         } else { 
    //             $proSum -= $proCur; 
    //         }    
    //     } 
    //     unset ($proArr); 
    //     return $result; 
    // }

    // /**
    //  * 可抽奖次数
    //  */
    // public function smashTimes()
    // {
    //     return out($this->egg());
    // }

    public function getWenZi()
    {
        $user = $this->user;

//        $sql = "SELECT
//                    a.prize_id,
//                    b.img_url
//                FROM
//                    mp_hongbao_signin_prize_log a
//                    LEFT JOIN mp_hongbao_signin_prize b ON a.prize_id = b.id
//                WHERE
//                    a.user_id = ".$user['id']."
//                    AND a.prize_id IN (1,2,3,4,6,7,8,17)
//                GROUP BY
//                    prize_id";
//        $data = Db::query($sql);
//        return out($data);
        $count1 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',1)->count();
        $count2 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',2)->count();
        $count3 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',3)->count();
        $count4 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',4)->count();

        $count6 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',6)->count();
        $count7 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',7)->count();
        $count8 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',8)->count();
        $count17 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',17)->count();

        $arr = [
            '1' => $count1,
            '2' => $count2,
            '3' => $count3,
            '4' => $count4,
            '6' => $count6,
            '7' => $count7,
            '8' => $count8,
            '17' => $count17,
        ];
        return out($arr);

    }
    public function meetingSignIn()
    {
        //第一个签到时间需要在上午10点到11点才能按动，第二个在19点到20点，
        //其他时间按提示：请在规定时间进行签到。
        //如果规定时间内，没有上传图片，点签到提示：请先上传有效参会图片。
        //如果两个签到都已经按要求完成，系统自动到账4元到团队奖励，这个4元让我后台可设置
        // 参数验证
        $req = $this->validate(request(), [
            'type|签到类型'      => 'require|in:1,2',
            'sign_in_image|签到图片' => 'require'
        ]);

        $user = $this->user; // 获取当前用户
        $userId = $user['id'];
        $realname = $user['realname'];
        $phone = $user['phone'];
        $type = $req['type'];
        $signInImage = $req['sign_in_image'];

        $currentHour = date('H'); // 获取当前小时
        $currentDate = date('Y-m-d'); // 获取当前日期

        // 检查签到时间是否符合要求
        if (($type == 1 && !($currentHour >= 10 && $currentHour < 11)) ||
            ($type == 2 && !($currentHour >= 19 && $currentHour < 20))) {
            return out(null, 10001, '请在规定时间进行签到');
        }

        // 检查今天是否已签到两次
        $signInCount = MeetingRecords::where('user_id', $userId)
            ->whereBetweenTime('created_at', "$currentDate 00:00:00", "$currentDate 23:59:59")
            ->count();

        if ($signInCount >= 2) {
            return out(null, 10002, '今日签到次数已达上限');
        }

        // 检查是否已在该时间段签到
        $hasSignedIn = MeetingRecords::where('user_id', $userId)
            ->where('type', $type)
            ->whereBetweenTime('created_at', "$currentDate 00:00:00", "$currentDate 23:59:59")
            ->count();

        if ($hasSignedIn) {
            return out(null, 10003, '本时段已签到，请勿重复签到');
        }
        Db::startTrans();
        // 插入签到记录
        $insert = MeetingRecords::insertGetId([
            'user_id' => $userId,
            'realname' => $realname,
            'phone' => $phone,
            'type' => $type,
            'sign_in_image' => $signInImage,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 再次检查是否已完成早晚签到
        $morningSigned = MeetingRecords::where('user_id', $userId)
            ->where('type', 1)
            ->whereBetweenTime('created_at', "$currentDate 00:00:00", "$currentDate 23:59:59")
            ->count();

        $eveningSigned = MeetingRecords::where('user_id', $userId)
            ->where('type', 2)
            ->whereBetweenTime('created_at', "$currentDate 00:00:00", "$currentDate 23:59:59")
            ->count();


        $dec = '签到成功';
        try {
            if ($morningSigned && $eveningSigned) {
                $rewardAmount = dbconfig('meeting_signin_amount') ; // 这里可以自定义奖励金额
                if($rewardAmount>0){
                    User::changeInc($userId,$rewardAmount,'xuanchuan_balance',58,$insert,2,'会议签到奖励');

                    $dec = "签到成功，今日签到完成，奖励 {$rewardAmount} 元！";
                }
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(null, 200,$dec );
    }

    public function meetingSignInStatus()
    {
        // 获取当前用户
        $user = $this->user;

        // 查询用户的签到记录（上午和下午）
        $currentDate = date('Y-m-d'); // 获取当前日期

        $signInRecords = MeetingRecords::where('user_id', $user['id'])
            ->whereIn('type', [1, 2])
            ->whereBetweenTime('created_at', "$currentDate 00:00:00", "$currentDate 23:59:59")
            ->select();

        // 初始化返回数据
        $data = [
            'am_sign_in' => '',
            'pm_sign_in' => ''
        ];

        // 遍历签到记录
        foreach ($signInRecords as $record) {
            if ($record['type'] == 1) {
                $data['am_sign_in'] = $record['sign_in_image'];
            } elseif ($record['type'] == 2) {
                $data['pm_sign_in'] = $record['sign_in_image'];
            }
        }
        return out($data);
    }

    public function meetingSignInRecord()
    {
        $user = $this->user;
        $data = MeetingRecords::where('user_id',$user['id'])->select();
        return out($data);
    }
}