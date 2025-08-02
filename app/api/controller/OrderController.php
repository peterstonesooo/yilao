<?php

namespace app\api\controller;

use app\model\AssetOrder;
use app\model\EnsureOrder;
use app\model\JijinOrder;
use app\model\HuodongOrder;
use app\model\Order;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\PassiveIncomeRecord;
use app\model\ProjectHuodong;
use app\model\RelationshipRewardLog;
use app\model\ReleaseWithdrawal;
use app\model\Taxoff;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserCardRecord;
use app\model\UserPrivateBank;
use app\model\UserRelation;
use app\model\UserSignin;
use app\model\YuanmengUser;
use app\model\YuanOrder;
use app\model\ZhufangOrder;
use think\facade\Cache;
use Exception;
use think\facade\Db;

class OrderController extends AuthController
{
    /**
     * project_group_id=1 status=0未发放 1已发放
     */
    public function placeOrder()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);

        $user = $this->user;

        $clickRepeatName = 'placeOrder-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '此项申领名额已售罄');
        }
        // 定义钱包字段
        $cash_wallet = 'topup_balance'; // 现金余额
        $team_wallet = 'xuanchuan_balance'; // 团队余额
        $red_packet_wallet = 'shenianhongbao'; // 红包钱包
        $salary_wallet = 'team_bonus_balance'; // 月薪钱包

        // 获取当前钱包余额
        $cash_balance = $user[$cash_wallet];
        $team_balance = $user[$team_wallet];
        $red_packet_balance = $user[$red_packet_wallet];
        $salary_balance = $user[$salary_wallet];

        // 总余额检查
        $total_balance = $cash_balance + $team_balance + $red_packet_balance + $salary_balance;

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::where('id', $req['project_id'])
                        ->find()
                        ->toArray();

            $pay_amount = $project['price'];

            if ($total_balance < $pay_amount) {
                return out(null, 10004, '余额不足，无法购买');
            }
            if (bccomp((string)$pay_amount, (string)$total_balance, 2) === 1) {
                exit_out(null, 10090, '余额不足');
            }

            $order_sn = 'JH'.build_order_sn($user['id']);

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'] ?? 1;
            $project['price'] = $pay_amount;
            $project['buy_amount'] = $pay_amount;
            $project['shouyi'] = $project['shouyi'];
            $project['project_id'] = $project['id'];
            $project['project_name'] = $project['name'];
            $project['project_group_id'] = $project['project_group_id'];
            $project['shengyu'] = $project['shengyu'];
            $project['shengyu_butie'] = $project['shengyu_butie'];
            $project['zhaiwujj'] = $project['zhaiwujj'];
            $project['buzhujin'] = $project['buzhujin'];
            $project['shouyijin'] = $project['shouyijin'];
            $project['next_bonus_time'] = time() + ($project['days'] * 86400);
            unset($project['id']);
            unset($project['created_at']);
            unset($project['updated_at']);
            $order = Order::create($project);
            $remaining_fee = $pay_amount;
            // 先扣除现金余额
            if ($cash_balance >= $remaining_fee) {
                User::changeInc($user['id'], -$remaining_fee, $cash_wallet, 3, $order['id'], 1, $project['name']."(现金余额)",0,1);
                $remaining_fee = 0;
            } else {
                if($cash_balance>0){
                    User::changeInc($user['id'], -$cash_balance, $cash_wallet, 3, $order['id'], 1, $project['name']."(现金余额)",0,1);
                    $remaining_fee = round($remaining_fee - $cash_balance, 2);
                }
            }

            // 如果现金余额不足，则从团队余额扣除剩余部分
            if ($remaining_fee > 0 && $team_balance > 0 ) {
                if ($team_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $team_wallet, 3, $order['id'], 1, $project['name']."(团队奖励)",0,1);
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$team_balance, $team_wallet, 3, $order['id'], 1, $project['name']."(团队奖励)",0,1);
                    $remaining_fee = round($remaining_fee - $team_balance, 2);
                }
            }

            // 如果团队余额不足，则 扣红包钱包
            if ($remaining_fee > 0 && $red_packet_balance > 0) {
                if ($red_packet_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $red_packet_wallet, 3, $order['id'], 1, $project['name']."(红包余额)", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$red_packet_balance, $red_packet_wallet, 3, $order['id'], 1, $project['name']."(红包余额)", 0, 1);
                    $remaining_fee = round($remaining_fee - $red_packet_balance, 2);

                }
            }

            // 如果红包钱包不足，则从月薪钱包扣除
            if ($remaining_fee > 0 && $salary_balance > 0) {
                if ($salary_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $salary_wallet, 55, $order['id'], 1, $project['name']."(月薪钱包)", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$salary_balance, $salary_wallet, 55, $order['id'], 1, $project['name']."(月薪钱包)", 0, 1);
                    $remaining_fee = round($remaining_fee - $salary_balance, 2);
                }
            }

            // 最终检查：支付是否完全完成
            if ($remaining_fee > 0) {
                return out(null, 10001, '余额不足，支付未完成');
            }

            // 订单支付完成
            Order::orderPayComplete($order['id'], $project, $user['id'],  $project['price']);
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];

            foreach ($relation as $v) {

                $reward = round(dbconfig($map[$v['level']])/100* $project['price'], 2);

                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

//            if ($project['project_group_id'] == 4 && $project['zhaiwujj'] > 0) {
//                if (time() >= strtotime('2024-11-13 00:00:00')) {
//                    $r = bcmul($project['zhaiwujj'], 2, 2);
//                    User::changeInc($user['id'], $r,'zhaiwujj',86,$order['id'],6, '债务基金','',1,'SR');
//                } else {
//                    User::changeInc($user['id'],$project['zhaiwujj'],'zhaiwujj',86,$order['id'],6, '债务基金','',1,'SR');
//                }
//            }
            User::changeInc($user['id'],$order['shouyi'],'income_balance',36,$order['id'],1,'钱包收益',0,2,'TD');

            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                active_tuandui($user);
            }

//            if ($project['name'] == "科技与社会发展"){
//                UserCardRecord::create([
//                    'user_id' => $user['id'],
//                    'fee' => $pay_amount,
//                    'status' => 2,//初始话办卡中
//                    'card_id' => 5,
//                    'bank_card' => '62'.time().$user['id'],
//                ]);
//            }
            //在平台消费（购买平台任何产品）一次,获得一次抽奖机会！
            $ret = User::where('id', $user['id'])->inc('huodong', 1)->update();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'data' => $ret['data'] ?? '']);

    }

    //养老金流转
    public function placeOrder1()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);

        $user = $this->user;
        if(time()>=strtotime(date('Y') . '-07-26 00:00:00')){
            return out(null, 10001, '流转已结束');
        }
        $clickRepeatName = 'placeOrder-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '此项申领名额已售罄');
        }

        $amount = $user['subsidy_amount']+$user['balance']+$user['income_balance'];

        //计算费用
        if ($amount <= 100000) {
            $fee = 1;
        } elseif ($amount >= 100000 && $amount <= 500000) {
            $fee = 2;
        } elseif ($amount >= 500000 && $amount <= 1000000) {
            $fee = 3;
        } elseif ($amount >= 1000000 && $amount <= 5000000) {
            $fee = 4;
        } elseif ($amount > 5000000) {
            $fee = 5;
        }
        // 定义钱包字段
        $cash_wallet = 'topup_balance'; // 现金余额
        $team_wallet = 'xuanchuan_balance'; // 团队余额
        $red_packet_wallet = 'shenianhongbao'; // 红包钱包
        $salary_wallet = 'team_bonus_balance'; // 月薪钱包

        // 获取当前钱包余额
        $cash_balance = $user[$cash_wallet];
        $team_balance = $user[$team_wallet];
        $red_packet_balance = $user[$red_packet_wallet];
        $salary_balance = $user[$salary_wallet];

        // 总余额检查
        $total_balance = $cash_balance + $team_balance + $red_packet_balance + $salary_balance;

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::where('id', $req['project_id'])
                ->find()
                ->toArray();
            $res = UserBalanceLog::where('user_id',$user['id'])->where('type',38)->find();
            if($res){
                return out(null, 10005, '不可重复流转');
            }
            if($fee!=$project['capital_amount_level']){
                return out(null, 10004, '流转资金，金额不符');
            }

            $pay_amount = $project['price'];

            if ($total_balance < $pay_amount) {
                return out(null, 10004, '余额不足，无法购买');
            }
            if (bccomp((string)$pay_amount, (string)$total_balance, 2) === 1) {
                exit_out(null, 10090, '余额不足');
            }

            $qianyue = UserBalanceLog::where('user_id',$user['id'])->where('type',28)->find();
            if(!$qianyue){
                return out(null, 10004, '请先完成收款银行卡授信签约');
            }

            $order_sn = 'JH'.build_order_sn($user['id']);

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'] ?? 1;
            $project['price'] = $pay_amount;
            $project['buy_amount'] = $pay_amount;
            $project['shouyi'] = $project['shouyi'];
            $project['project_id'] = $project['id'];
            $project['project_name'] = $project['name'];
            $project['project_group_id'] = $project['project_group_id'];
            $project['shengyu'] = $project['shengyu'];
            $project['shengyu_butie'] = $project['shengyu_butie'];
            $project['zhaiwujj'] = $project['zhaiwujj'];
            $project['buzhujin'] = $project['buzhujin'];
            $project['shouyijin'] = $project['shouyijin'];
            $project['liuzhuan'] = $amount;
//            $project['next_bonus_time'] = time() + (86400);//明天 这个时候
            $project['next_bonus_time'] = strtotime(date('Y') . '-07-26 00:00:00');//7月26日的 0 点时间戳（今年）

            unset($project['id']);
            unset($project['created_at']);
            unset($project['updated_at']);
            $order = Order::create($project);
            $remaining_fee = $pay_amount;
            // 先扣除现金余额
            if ($cash_balance >= $remaining_fee) {
                User::changeInc($user['id'], -$remaining_fee, $cash_wallet, 3, $order['id'], 1, $project['name']."(现金余额)",0,1);
                $remaining_fee = 0;
            } else {
                if($cash_balance>0){
                    User::changeInc($user['id'], -$cash_balance, $cash_wallet, 3, $order['id'], 1, $project['name']."(现金余额)",0,1);
                    $remaining_fee = round($remaining_fee - $cash_balance, 2);
                }
            }

            // 如果现金余额不足，则从团队余额扣除剩余部分
            if ($remaining_fee > 0 && $team_balance > 0 ) {
                if ($team_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $team_wallet, 3, $order['id'], 1, $project['name']."(团队奖励)",0,1);
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$team_balance, $team_wallet, 3, $order['id'], 1, $project['name']."(团队奖励)",0,1);
                    $remaining_fee = round($remaining_fee - $team_balance, 2);
                }
            }

            // 如果团队余额不足，则 扣红包钱包
            if ($remaining_fee > 0 && $red_packet_balance > 0) {
                if ($red_packet_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $red_packet_wallet, 3, $order['id'], 1, $project['name']."(红包余额)", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$red_packet_balance, $red_packet_wallet, 3, $order['id'], 1, $project['name']."(红包余额)", 0, 1);
                    $remaining_fee = round($remaining_fee - $red_packet_balance, 2);

                }
            }

            // 如果红包钱包不足，则从月薪钱包扣除
            if ($remaining_fee > 0 && $salary_balance > 0) {
                if ($salary_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $salary_wallet, 55, $order['id'], 1, $project['name']."(月薪钱包)", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$salary_balance, $salary_wallet, 55, $order['id'], 1, $project['name']."(月薪钱包)", 0, 1);
                    $remaining_fee = round($remaining_fee - $salary_balance, 2);
                }
            }

            // 最终检查：支付是否完全完成
            if ($remaining_fee > 0) {
                return out(null, 10001, '余额不足，支付未完成');
            }

            // 订单支付完成
            Order::orderPayComplete($order['id'], $project, $user['id'],  $project['price']);

            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];

            foreach ($relation as $v) {

                $reward = round(dbconfig($map[$v['level']])/100* $project['price'], 2);

                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            User::changeInc($user['id'],$pay_amount,'balance',38,$order['id'],1,'流转手续费返还',0,2,'TD');

            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                active_tuandui($user);
            }

            //在平台消费（购买平台任何产品）一次,获得一次抽奖机会！
//            $ret = User::where('id', $user['id'])->inc('huodong', 1)->update();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'data' => $ret['data'] ?? '']);

    }

    public function placeOrder2()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);

        $user = $this->user;

        $clickRepeatName = 'placeOrder-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '此项申领名额已售罄');
        }

        //只能购买1次
        $gaojiContent = Order::where('user_id',$user['id'])->where('project_name','高级用户')->count();
        if ($gaojiContent >= 1){
            return out(null, 10001, '只能购买一次');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::where('id', $req['project_id'])
                ->find()
                ->toArray();

            $pay_amount = $project['price'];

            if ($pay_amount > $user['topup_balance']) {
                exit_out(null, 10090, '余额不足');
            }

            $order_sn = 'JH'.build_order_sn($user['id']);

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'] ?? 1;
            $project['price'] = $pay_amount;
            $project['buy_amount'] = $pay_amount;
            $project['shouyi'] = $project['shouyi'];
            $project['project_id'] = $project['id'];
            $project['project_name'] = $project['name'];
            $project['shengyu'] = $project['shengyu'];
            $project['shengyu_butie'] = $project['shengyu_butie'];
            $project['zhaiwujj'] = $project['zhaiwujj'];
            $project['buzhujin'] = $project['buzhujin'];
            $project['shouyijin'] = $project['shouyijin'];
            $project['ributie'] = $project['ributie'];
            $project['yuebutie'] = $project['yuebutie'];
            unset($project['id']);
            unset($project['created_at']);
            unset($project['updated_at']);
            $order = Order::create($project);

            // 扣余额
            User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['name'],0,1);
            // 订单支付完成
            Order::orderPayComplete($order['id'], $project, $user['id'], $pay_amount);

            // 给上3级团队奖（迁移至申领）
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            if ($project['project_group_id'] == 4 && $project['zhaiwujj'] > 0) {
                if (time() >= strtotime('2024-11-13 00:00:00')) {
                    $r = bcmul($project['zhaiwujj'], 2, 2);
                    User::changeInc($user['id'], $r,'zhaiwujj',86,$order['id'],6, '债务基金','',1,'SR');
                } else {
                    User::changeInc($user['id'],$project['zhaiwujj'],'zhaiwujj',86,$order['id'],6, '债务基金','',1,'SR');
                }
            }

            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }

            if ($project['name'] == "科技与社会发展"){
                UserCardRecord::create([
                    'user_id' => $user['id'],
                    'fee' => $pay_amount,
                    'status' => 2,//初始话办卡中
                    'card_id' => 5,
                    'bank_card' => '62'.time().$user['id'],
                ]);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'data' => $ret['data'] ?? '']);

    }
























    public function huodongPlaceOrder()
    {
        $user = $this->user;

        //return out(null, 10001, '该活动已结束');
        $user = User::where('id', $user['id'])->find();
        if($user['huodong']<1) {
            return out(null, 10001, '您不满足抽奖条件');
        }
        $time = time();
        // if($time < 1724083200 || $time > 1722441599) {
        //     return out(null, 10001, '该活动已结束');
        // }
        $proArr = [
            array('id' => 1, 'name' => 88,  'amount' => 88, 'v' => 1),
            array('id' => 2, 'name' => 188, 'amount' => 188, 'v' => 5),
            array('id' => 3, 'name' => 288, 'amount' => 288, 'v' => 10),
            array('id' => 4, 'name' => 388, 'amount' => 388, 'v' => 12),
        ];

        Db::startTrans(); // 开启事务
        try {
            // 计算权重总和
            $proSum = array_sum(array_column($proArr, 'v'));

            // 生成随机数
            $randNum = mt_rand(1, $proSum);

            // 初始化变量，存储抽奖结果
            $currentSum = 0;
            $result = null;

            // 遍历奖品数组，根据权重抽奖
            foreach ($proArr as $item) {
                $currentSum += $item['v']; // 累加当前奖品的权重
                if ($randNum <= $currentSum) {
                    $result = $item; // 选中当前奖品
                    break;
                }
            }

            // 确保抽奖结果非空
            if (!$result) {
                return out(null, 10001, '抽奖失败，请重试！');
                //throw new Exception('抽奖失败，未能选中任何奖品');
            }

            // 奖品发放逻辑
            User::changeInc($user['id'], $result['amount'], 'balance', 56, 1, 1, '幸运大抽奖 ' );

            // 更新活动次数
            User::where('id', $user['id'])->dec('huodong', 1)->update();

            // 插入活动订单记录
            $insert = [
                'user_id' => $user['id'],
                'phone'   => $user['phone'],
                'name'    => $result['name'],
                'amount'  => $result['amount'],
            ];
            HuodongOrder::create($insert);

            Db::commit(); // 提交事务
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out($result);

    }

    public function releaseOrderList()
    {
        $user = $this->user;
        $data = ReleaseWithdrawal::where('user_id', $user['id'])->order('id', 'desc')->paginate(10,false,['query'=>request()->param()]);

        return out(['data' => $data]);
    }

    public function releasePlaceOrder()
    {
        $req = $this->validate(request(), [
            'amount|申请提现额度' => 'require',
            'sn_no|提现序列号' => 'require',
            'is_check' => 'require',
        ]);

        $user = $this->user;

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();

            if($req['is_check'] && $user['private_release'] > 0) {
                return out(['private_release' => $user['private_release']], 19999);
            }

            if($user['sn_no'] != $req['sn_no']) {
                return out(null, 10001, '序列号错误');
            }

            $shenbao = $user['jijin_shenbao_amount'] + $user['yuan_shenbao_amount'];

            if($req['is_check']) {
                if($shenbao < 1000000) {
                    if($req['amount'] < 100 || $req['amount'] > 3000) {
                        return out(null, 10001, '申请提现额度于纳税额度不符');
                    }
                } elseif ($shenbao >= 1000000 && $shenbao < 5000000) {
                    if($req['amount'] < 100 || $req['amount'] > 4000) {
                        return out(null, 10001, '申请提现额度于纳税额度不符');
                    }
                } elseif ($shenbao >= 5000000) {
                    if($req['amount'] < 100 || $req['amount'] > 5000) {
                        return out(null, 10001, '申请提现额度于纳税额度不符');
                    }
                }
            }

            $count = ReleaseWithdrawal::where('user_id', $user['id'])->whereIn('status', [1,2,3])->count();
            if($count) {
                return out(null, 10001, '您有未释放的提现额度申请，无法再次申请');
            }
            $insert = [
                'user_id' => $user['id'],
                'amount' => $req['amount'],
            ];
            ReleaseWithdrawal::create($insert);


            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function zhufangList()
    {
        
        $user = $this->user;

        $data = ZhufangOrder::where('user_id', $user['id'])->find();

        return out(['data' => $data]);
    }

    public function zhufangPlaceOrder()
    {
        $req = $this->validate(request(), [
            'province_name|省' => 'require',
            'city_name|市' => 'require',
            'area|区(县)' => 'require',
            'pingfang|平方' => 'require',
        ]);

        $user = $this->user;


        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();   
            if($user['jijin_shenbao_amount'] <= 0) {
                if($user['yuan_shenbao_amount'] > 0) {
                    return out(null, 10001, '您不符合领取条件');
                } else {
                    return out(null, 10001, '请先申报纳税');
                }
            }

            $shenbao = $user['jijin_shenbao_amount'];
            
            if($shenbao < 1000000) {
                $pingfang = 88;
            } elseif ($shenbao >= 1000000 && $shenbao < 5000000) {
                $pingfang = 128;
            } elseif ($shenbao >= 5000000) {
                $pingfang = 168;
            }

            if($pingfang != $req['pingfang']) {
                return out(null, 10001, '申报金额与选择面积大小不一致');
            }

            $order = ZhufangOrder::where('user_id', $user['id'])->find();
            if($order && $order['pingfang'] >= $req['pingfang']) {
                return out(null, 10001, '您已经领取过保障性住房');
            }
            if($order) {
                $update = [
                    'pingfang' => $req['pingfang'],
                    'province_name' => $req['province_name'],
                    'city_name' => $req['city_name'],
                    'area' => $req['area'],
                ];
                ZhufangOrder::where('id', $order['id'])->update($update);
            } else {

                $insert = [
                    'user_id' => $user['id'],
                    'pingfang' => $req['pingfang'],
                    'province_name' => $req['province_name'],
                    'city_name' => $req['city_name'],
                    'area' => $req['area'],
                ];
                ZhufangOrder::create($insert);
            }


            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function jijinShenbaoList()
    {
        
        $user = $this->user;

        $data = JijinOrder::where('user_id', $user['id'])->paginate(10,false,['query'=>request()->param()]);

        return out(['data' => $data]);
    }

    public function yuanShenbaoList()
    {

        $user = $this->user;

        $data = YuanOrder::where('user_id', $user['id'])->paginate(10,false,['query'=>request()->param()]);

        return out(['data' => $data]);
    }

    public function yuanShenbaoPlaceOrder()
    {

        $req = $this->validate(request(), [
            'amount|最终纳税金额' => 'require',
            'is_off|税务抵用券' => 'require',
            'shenbao_amount|申报金额' => 'require',
            'pay_password|支付密码' => 'require',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();

            $pay_amount = $req['amount'];
            if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'],-$pay_amount,'topup_balance',57,$user['id'],1);
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',57,$user['id'],1);
                $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',57,$user['id'],1);
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',57,$user['id'],1);
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',57,$user['id'],1);
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',57,$user['id'],1);
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',57,$user['id'],1);
                    }
                    
                }
            }
            // 扣余额
            //User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1);
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$user['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user['id'])->inc('30_6_invest_amount', $pay_amount)->update();
            }
            User::upLevel($user['id']);

            if($req['is_off'] > 0) {
                Taxoff::where('user_id', $user['id'])->where('off', $req['is_off'])->update(['status'=> 1]);
            };

            User::where('id',$user['id'])->inc('yuan_shenbao_amount',$req['shenbao_amount'])->update();
            $insert = [
                'user_id' => $user['id'],
                'amount' => $req['amount'],
                'is_off' => $req['is_off'],
                'shenbao_amount' => $req['shenbao_amount'],
            ];
            YuanOrder::create($insert);

            User::where('id',$user['id'])->inc('private_bank_balance',$req['shenbao_amount'])->update();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();

    }

    public function jijinShenbaoPlaceOrder()
    {

        $req = $this->validate(request(), [
            'amount|最终纳税金额' => 'require',
            'is_off|税务抵用券' => 'require',
            'shenbao_amount|申报金额' => 'require',
            'pay_password|支付密码' => 'require',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();

            $pay_amount = $req['amount'];
            if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'],-$pay_amount,'topup_balance',58,$user['id'],1);
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',58,$user['id'],1);
                $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',58,$user['id'],1);
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',58,$user['id'],1);
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',58,$user['id'],1);
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',58,$user['id'],1);
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',58,$user['id'],1);
                    }
                    
                }
            }
            // 扣余额
            //User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1);
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$user['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user['id'])->inc('30_6_invest_amount', $pay_amount)->update();
            }
            User::upLevel($user['id']);

            if($req['is_off'] > 0) {
                Taxoff::where('user_id', $user['id'])->where('off', $req['is_off'])->update(['status'=> 1]);
            };

            User::where('id',$user['id'])->inc('jijin_shenbao_amount',$req['shenbao_amount'])->update();
            $insert = [
                'user_id' => $user['id'],
                'amount' => $req['amount'],
                'is_off' => $req['is_off'],
                'shenbao_amount' => $req['shenbao_amount'],
            ];
            JijinOrder::create($insert);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();

    }

    public function taxPlaceOrder()
    {
        $req = $this->validate(request(), [
            'off|税务抵用券' => 'require|in:5,8',
        ]);

        $user = $this->user;

        $time  = time();
        return out(null, 10001, '领取时间不在范围内');
        // if($req['off'] == 5) {
        //     if($time < 1722441600 || $time > 1723305599) {
        //         return out(null, 10001, '领取时间不在范围内');
        //     }
        // }

        // if($req['off'] == 8) {
        //     if($time < 1723305600 || $time > 1724169599) {
        //         return out(null, 10001, '领取时间不在范围内');
        //     }
        // }

        // $tax = Taxoff::where('user_id', $user['id'])->where('off', $req['off'])->find();

        // if($tax) {
        //     return out(null, 10001, '您已经领取过折扣税务抵用券');
        // }

        $insert = [
            'user_id' => $user['id'],
            'off' => $req['off'],
        ];
        Taxoff::create($insert);
        return out();
    }

    public function openBondPlaceOrder()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            if($user['bond_open'] == 1) {
                exit_out(null, 10001, '您已经开户债券托管账户');
            }

            if($user['private_bank_open'] == 0) {
                exit_out(null, 10001, '请先开户私人银行');
            }

            $pay_amount = 236;
            if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'],-$pay_amount,'topup_balance',50,$user['id'],1);
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',50,$user['id'],1);
                $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',50,$user['id'],1);
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',50,$user['id'],1);
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',50,$user['id'],1);
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',50,$user['id'],1);
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',50,$user['id'],1);
                    }
                    
                }
            }
            // 扣余额
            //User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1);
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$user['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user['id'])->inc('30_6_invest_amount', $pay_amount)->update();
            }
            User::where('id',$user['id'])->inc('huodong',1)->update();
            User::upLevel($user['id']);

            User::where('id',$user['id'])->update(['bond_open' => 1]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function openBankPlaceOrder()
    {
        $req = $this->validate(request(), [
            'name|真实姓名' => 'require',
            'phone|手机号' => 'require',
            'id_card|身份证号' => 'require',
            'pay_password|支付密码' => 'require',
            'bank_password|设置密码' => 'require',
            're_bank_password|重复密码' => 'require',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        if($req['bank_password'] != $req['re_bank_password']) {
            return out(null, 10001, '您两次输入的密码不一致');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            if($user['private_bank_open'] == 1) {
                exit_out(null, 10001, '您已经开户私人专属银行');
            }

            if($user['all_digit_balance'] >= 6000000) {
                $pay_amount = 0;
            } else {
                $pay_amount = 980;
            }
            if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            $part1 = '621788';
            $part2 =  str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            //$part2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $part3 = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

            $num = [
                1 => 1111,
                2 => 2222,
                3 => 3333,
                4 => 4444,
                5 => 5555,
                6 => 6666,
                7 => 7777,
                8 => 8888,
                9 => 9999,
                10 => 0000,
            ];

            $part4 = $num[mt_rand(1,10)];
        
            $randomNumber = $part1.$part2.$part3.$part4;

            $insert['name'] = $req['name'];
            $insert['user_id'] = $user['id'];
            $insert['phone'] = $req['phone'];
            $insert['id_card'] = $req['id_card'];
            $insert['bank_code'] = $randomNumber;
            $order = UserPrivateBank::create($insert);

            if($pay_amount > 0) {
                if($user['topup_balance'] >= $pay_amount) {
                    User::changeInc($user['id'],-$pay_amount,'topup_balance',49,$order['id'],1);
                } else {
                    User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',49,$order['id'],1);
                    $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                    if($user['team_bonus_balance'] >= $topup_amount) {
                        User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',49,$order['id'],1);
                    } else {
                        User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',49,$order['id'],1);
                        $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                        if($user['balance'] >= $signin_amount) {
                            User::changeInc($user['id'],-$signin_amount,'balance',49,$order['id'],1);
                        } else {
                            User::changeInc($user['id'],-$user['balance'],'balance',49,$order['id'],1);
                            $balance_amount = bcsub($signin_amount, $user['balance'],2);
                            User::changeInc($user['id'],-$balance_amount,'release_balance',49,$order['id'],1);
                        }
                        
                    }
                }

                // 扣余额
                //User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1);
                
                // 给上3级团队奖
                $relation = UserRelation::where('sub_user_id', $user['id'])->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                    if($reward > 0){
                        User::changeInc($v['user_id'],$reward,'balance',8,$order['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                        RelationshipRewardLog::insert([
                            'uid' => $v['user_id'],
                            'reward' => $reward,
                            'son' => $user['id'],
                            'son_lay' => $v['level'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            }


            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user['id'])->inc('30_6_invest_amount', $pay_amount)->update();
            }
            User::where('id',$user['id'])->inc('huodong',1)->update();
            User::upLevel($user['id']);

            User::where('id',$user['id'])->update(['private_bank_open' => 1, 'bank_password' => sha1(md5($req['bank_password']))]);
            $data = Order::where('user_id', $user['id'])->where('status', 6)->where('project_group_id', 2)->select();
            $money = 0;
            foreach ($data as $key => $value) {
                $add = bcadd($value['gain_bonus'], $value['all_bonus'], 2);
                $money += bcsub($add, $value['checkingAmount'], 2);
            }
            User::changeInc($user['id'],$money,'private_bank_balance',53,$order['id'],1);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }


    public function shuhuiPlaceOrder()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
            'pay_amount' => 'require|number',
            'shuhui_img_url|签名凭证' => 'require|url',
        ]);

        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $order = Order::where('id', $req['order_id'])->find();
        if(!$order){
            return out(null, 10001, '项目不存在');
        }

        if($order['status'] == 2) {
            return out(null, 10001, '基金还未到期');
        }

        Db::startTrans();
        try {
            $pay_amount = $req['pay_amount'];
            if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10090, '余额不足');
            }

            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                $topup_amount = bcsub($pay_amount, $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',3,$order['id'],1,'基金赎回'.$order['project_name'],0,1);
                    }
                }
            }
            // 扣余额
            //User::changeInc($user['id'],-$pay_amount,'topup_balance',3,$order['id'],1,$project['project_name'],0,1);
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$order['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user['id'])->inc('30_6_invest_amount', $pay_amount)->update();
            }
            User::where('id',$user['id'])->inc('huodong',1)->update();
            User::upLevel($user['id']);

            $currentDate = date("Y-m-d"); // 假设这是交易日
            $holidays = ['2024-06-08', '2024-06-09', '2024-06-10']; // 假设这是假期列表
            
            $nextTradeDay = $this->getNextTradeDay($currentDate, $holidays);

            //Order::where('id', $req['order_id'])->update(['status' => 5, 'shuhui_img_url' => $req['shuhui_img_url'], 'shuhui_time' => date('Y-m-d H:i:s'), 'shuhui_end_time' => strtotime($nextTradeDay)]);

            $add = bcadd($order['gain_bonus'], $order['all_bonus'], 2);
            $money = bcsub($add, $order['checkingAmount'], 2);

            $user = User::where('id', $order['user_id'])->find();
            if($user['private_bank_open'] == 1) {
                User::changeInc($order['user_id'],$money,'private_bank_balance',53,$order['id'],1, '银联入账');
                User::where('id', $order['user_id'])->inc('all_digit_balance', $money)->update();
            } else {
                User::where('id', $order['user_id'])->inc('digit_balance', $money)->update();
                User::where('id', $order['user_id'])->inc('all_digit_balance', $money)->update();
            }
            Order::where('id',$order->id)->update(['status'=>6, 'shuhui_img_url' => $req['shuhui_img_url'], 'shuhui_time' => date('Y-m-d H:i:s'), 'shuhui_end_time' => strtotime($nextTradeDay)]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }


    public function getNextTradeDay($currentDate, $holidays = []) {
        // 将日期转换为时间戳
        $currentTimestamp = strtotime($currentDate);
        
        // 增加1天，然后逐日增加直到找到下一个工作日
        $nextTradeDay = date('Y-m-d', $currentTimestamp + 86400); // 加一天
        while (in_array($nextTradeDay, $holidays) || (date('w', strtotime($nextTradeDay)) == 0 || date('w', strtotime($nextTradeDay)) == 6)) {
            $nextTradeDay = date('Y-m-d', strtotime($nextTradeDay . ' +1 day'));
        }
        $nextTradeDay = date('Y-m-d', strtotime($nextTradeDay . ' +1 day'));
        while (in_array($nextTradeDay, $holidays) || (date('w', strtotime($nextTradeDay)) == 0 || date('w', strtotime($nextTradeDay)) == 6)) {
            $nextTradeDay = date('Y-m-d', strtotime($nextTradeDay . ' +1 day'));
        }
        return $nextTradeDay;
    }

    public function shuhui_img()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'shuhui_img_url|签名凭证' => 'require|url',
        ]);

        $user = $this->user;

        $order = Order::where('id', $req['id'])->find();
        if(!$order) {
            exit_out(null, 10001, '订单不存在');
        }
        if($order['is_shuhui_confirm'] == 1) {
            exit_out(null, 10001, '您的订单已经确认，无法修改签名');
        }

        $res = Order::where('id', $req['id'])->update(['shuhui_img_url' => $req['shuhui_img_url']]);
        return out();
    }

    public function shuhui_confirm()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);

        $user = $this->user;

        $order = Order::where('id', $req['id'])->find();
        if(!$order) {
            exit_out(null, 10001, '订单不存在');
        }

        $res = Order::where('id', $req['id'])->update(['is_shuhui_confirm' => 1]);
        return out();
    }

    public function lvyouPlaceOrder()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
        ]);

        $user = $this->user;

        $project = Project::where('id', $req['project_id'])->where('project_group_id',4)->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '项目不存在');
        }

/*         $redis = new \Predis\Client(config('cache.stores.redis'));
        $ret = $redis->set('order_'.$user['id'],1,'EX',5,'NX');
        if(!$ret){
            return out("服务繁忙，请稍后再试");
        } */

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,min_amount,single_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method')
                        ->where('id', $req['project_id'])
                        ->lock(true)
                        ->append(['all_total_buy_num'])
                        ->find()
                        ->toArray();



            $pass = !1;
            if($user['fuli_total_count'] == 0) {
                $data = UserRelation::alias('r')->leftJoin('user u', 'u.id = r.sub_user_id')
                    ->where('u.is_active', 1)
                    ->where('r.level', 1)
                    ->where('r.user_id', $user['id'])
                    ->field('r.user_id,count(*) as count')
                    ->group('r.user_id')
                    ->find();
                if(!$data) {
                    exit_out(null, 10001, '您的邀请人数不足');
                }
                if($data['count'] >= dbconfig('fuli_total')){
                    $pass = !0;
                    User::where('id', $user['id'])->update([
                        'fuli_total_count' => 1
                    ]);
                }
            }

            if(!$pass){
                $data = UserRelation::alias('r')->leftJoin('user u', 'u.id = r.sub_user_id')
                        ->where('u.active_time', '>=', 1716134400)
                        ->where('u.is_active', 1)
                        ->where('r.level', 1)
                        ->where('r.user_id', $user['id'])
                        ->field('r.user_id,count(*) as count')
                        ->group('r.user_id')
                        ->find();
                if(!$data) {
                    exit_out(null, 10001, '您的邀请人数不足');
                }
                $count = $data['count'] - $user['fuli_base_count'];
                $times = floor($count / dbconfig('fuli_base'));
                if($times >= 1) {
                    $pass = !0;
                    User::where('id', $user['id'])->inc('fuli_base_count', dbconfig('fuli_base'))->update();
                } else {
                    exit_out(null, 10001, '您的邀请人数不足');
                }
            }
            

            $order_sn = build_order_sn($user['id'],"SN");
            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['price'] = $project['single_amount'];
            $project['buy_amount'] = $project['single_amount'];
            $project['status'] = 4;
            $project['pay_time'] = time();

            $order = Order::create($project);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function fuliPlaceOrder()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
        ]);

        $user = $this->user;

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '项目不存在');
        }

/*         $redis = new \Predis\Client(config('cache.stores.redis'));
        $ret = $redis->set('order_'.$user['id'],1,'EX',5,'NX');
        if(!$ret){
            return out("服务繁忙，请稍后再试");
        } */

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,min_amount,single_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method')
                        ->where('id', $req['project_id'])
                        ->lock(true)
                        ->append(['all_total_buy_num'])
                        ->find()
                        ->toArray();
            
            //判断消费金额是否满足
            // if($user['30_6_invest_amount'] < $project['min_amount']) {
            //     return out(null, 10001, '领取失败，您在活动期间累计消费不足');
            // }
            // $count = Order::where('user_id', $user['id'])->where('project_group_id',1)->count();
            // if($count) {
            //     return out(null, 10001, '您已经领取过福利了');
            // }
            $today = date("Y-m-d 00:00:00", time());
            $end = date("Y-m-d 23:59:59", time());
            $count = Order::where('project_group_id', 1)->where('created_at','>=', $today)->where('created_at', '<=', $end)->count();
            $total_quota = dbconfig('total_quota');
            $remaining_quota = dbconfig('remaining_quota');
            if(($remaining_quota * $count) >= $total_quota) {
                exit_out(null, 10001, '剩余数量为0');
            }
            if($project['project_id'] == 77) {
                $count = Order::where(function ($query){
                    $query->whereOr('project_id', 78);
                    $query->whereOr('project_id', 79);
                })->where('user_id', $user['id'])->where('is_gift', 0)->find();
                if(!$count) {
                    exit_out(null, 10001, '请先申购相应的三农基金');
                }
                Order::where('id', $count['id'])->update([
                    'is_gift' => 1
                ]);
            }
            if($project['project_id'] == 76) {
                $count = Order::where(function ($query){
                    $query->whereOr('project_id', 80);
                    $query->whereOr('project_id', 81);
                })->where('user_id', $user['id'])->where('is_gift', 0)->find();
                if(!$count) {
                    exit_out(null, 10001, '请先申购相应的三农基金');
                }
                Order::where('id', $count['id'])->update([
                    'is_gift' => 1
                ]);
            }
            if($project['project_id'] == 75) {
                $count = Order::where('project_id', 82)->where('user_id', $user['id'])->where('is_gift', 0)->find();
                if(!$count) {
                    exit_out(null, 10001, '请先申购相应的三农基金');
                }
                Order::where('id', $count['id'])->update([
                    'is_gift' => 1
                ]);
            }
            if($project['project_id'] == 84) {
                $count = Order::where('project_id', 83)->where('user_id', $user['id'])->where('is_gift', 0)->find();
                if(!$count) {
                    exit_out(null, 10001, '请先申购相应的三农基金');
                }
                Order::where('id', $count['id'])->update([
                    'is_gift' => 1
                ]);
            }
            

            $order_sn = build_order_sn($user['id'],"SN");
            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['price'] = $project['single_amount'];
            $project['buy_amount'] = $project['single_amount'];
            $project['status'] = 4;
            $project['pay_time'] = time();

            $order = Order::create($project);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function assetPlaceOrder()
    {
        $req = $this->validate(request(), [
            'type' => 'require|number',
            'name|姓名'=> 'require',
            'phone|手机号' => 'require|mobile',
            'id_card|身份证号' => 'require',
            // 'balance|账户余额' => 'require|number',
            'digital_yuan_amount|数字人民币' => 'number',
            // 'poverty_subsidy_amount|生活补助' => 'require|number',
            'level|共富等级' => 'require|number',
            'ensure|共富保障' => 'max:100',
            'rich|共富方式' => 'require|number',
            'pay_password|支付密码' => 'require',
        ]);
        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $count = AssetOrder::where('user_id', $user['id'])->where('status', 2)->count();
        if($count) {
            return out(null, 10111, '您已提交，等待交接完成');
        }
       // $min_asset = config('map.asset_recovery')[$req['type']]['min_asset'] * 10000;
        $max_asset = config('map.asset_recovery')[$req['type']]['max_asset'];
        // if(($req['balance'] + $req['digital_yuan_amount'] + $req['poverty_subsidy_amount']) > $max_asset || ($req['balance'] + $req['digital_yuan_amount'] + $req['poverty_subsidy_amount']) < $min_asset) {
        //     return out(null, 10110, '恢复资产超过限制');
        // }
        if(!isset($req['digital_yuan_amount']) || !$req['digital_yuan_amount']) {
            if($max_asset == 'max') {
                $req['digital_yuan_amount'] = 50000000;
            } else {
                $req['digital_yuan_amount'] = $max_asset * 10000;
            }
            
        }

        $amount = config('map.asset_recovery')[$req['type']]['amount'];

        if ($amount >  ($user['topup_balance'] + $user['signin_balance'] + $user['team_bonus_balance'] + $user['release_balance'])) {
            exit_out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {
            $req['user_id'] = $user['id'];
            $req['order_sn'] = 'GF'.build_order_sn($user['id']);
            $req['status'] = 2;
            $req['next_return_time'] = strtotime("+25 day", strtotime(date('Y-m-d')));
            $req['next_reward_time'] = strtotime("+72 hours");
            $order = AssetOrder::create($req);

            
            //购买产品和资产恢复都要激活用户
            $userUpdate = ['can_open_digital' => 1,'invest_amount'=>Db::raw('invest_amount+'.$amount)];
            if ($user['is_active'] == 0) {
                $userUpdate['is_active'] = 1;
                $userUpdate['active_time'] = time();
                // 下级用户激活
                \app\model\UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }
            //扣余额
            if($user['team_bonus_balance'] >= $amount) {
                User::changeInc($user['id'],-$amount,'team_bonus_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
            } else {
                User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
                $topup_amount = $amount - $user['team_bonus_balance'];
                if($user['topup_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'topup_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
                } else {
                    User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',25,$order['id'],1,'资产交接',0,1,'JJ');
                    $signin_amount = $topup_amount - $user['topup_balance'];
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',49,$order['id'],1);
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',49,$order['id'],1);
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',49,$order['id'],1);
                    }
                }
            }
            //User::changeInc($user['id'],-$amount,'topup_balance',25,$order['id'],1, '资产交接',0,1,'JJ');
            User::where('id', $user['id'])->update($userUpdate);

            //下单保障项目
            if(isset($req['ensure']) && !empty($req['ensure'])) {
                $ensure = [];
                $ensure = explode(',', $req['ensure']);
                foreach ($ensure as $key => $value) {
                    $data = config('map.ensure')[$value];
                    $insert['user_id'] = $user['id'];
                    $insert['order_sn'] = 'GF'.build_order_sn($user['id']);
                    $insert['status'] = 2;
                    $insert['ensure'] = $value;
                    $insert['amount'] = $data['amount'];
                    $insert['receive_amount'] = $data['receive_amount'];
                    $insert['process_time'] = $data['process_time'];
                    $insert['verify_time'] = $data['verify_time'];
                    $insert['next_reward_time'] = strtotime("+{$data['process_time']} day", strtotime(date('Y-m-d')));
                    $insert['next_return_time'] = strtotime("+{$data['verify_time']} day", strtotime(date('Y-m-d')));
                    $order = EnsureOrder::create($insert);
                }
            }

            User::upLevel($user['id']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(['order_id' => $order['id'] ?? 0]);
    }

    public function assetOrderConfig()
    {
        return out(
            config('map.asset_recovery')
        );
    }

    public function assetOrderList()
    {

        $user = $this->user;

        $order = AssetOrder::where('user_id', $user['id'])->where('status', 2)->find();

        $data = config('map.ensure');

        if($order) {
            $ensure = explode(',', $order['ensure']);
            foreach ($ensure as $value) {
                $data[$value]['receive'] = !0;
            }
        }
        foreach ($data as $key => $value) {
            if(EnsureOrder::where('user_id', $user['id'])->where('ensure', $value['id'])->count()) {
                $data[$key]['receive'] = !0;
            }
        }
        unset($data[1]);
        return out($data);
    }

    public function receivePlaceOrder()
    {
        return out(null,10010,'名额已满');
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;

        $data = config('map.ensure')[$req['id']];

        if ($data['amount'] >  $user['topup_balance']) {
            exit_out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {
            $insert['user_id'] = $user['id'];
            $insert['order_sn'] = 'GF'.build_order_sn($user['id']);
            $insert['status'] = 2;
            $insert['ensure'] = $req['id'];
            $insert['amount'] = $data['amount'];
            $insert['receive_amount'] = $data['receive_amount'];
            $insert['process_time'] = $data['process_time'];
            $insert['verify_time'] = $data['verify_time'];
            $insert['next_reward_time'] = strtotime("+{$data['process_time']} day", strtotime(date('Y-m-d')));
            $insert['next_return_time'] = strtotime("+{$data['verify_time']} day", strtotime(date('Y-m-d')));
            $order = EnsureOrder::create($insert);
            User::changeInc($user['id'],-$data['amount'],'topup_balance',26,$order['id'],1,'',0,1,'BZ');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out(['order_id' => $order['id'] ?? 0]);
    }

    public function placeOrder_bak()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'buy_num' => 'require|number|>:0',
            'pay_method' => 'require|number',
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
            'pay_voucher_img_url|支付凭证' => 'requireIf:pay_method,6|url',
        ]);
        $user = $this->user;

/*         if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        } */
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->where('status',1)->find();
        if(!$project){
            return out(null, 10001, '项目不存在');
        }
        if($project['project_group_id']==2){
            $order = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',1)->find();
            if(!$order){
                return out(null, 10001, '请先购买强国工匠项目');
            }
        }

        if($project['project_group_id']==3){
            $order = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',1)->find();
            $order2 = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('project_group_id',2)->find();
            if(!$order || !$order2){
                return out(null, 10001, '请先购买强国工匠和国富民强项目');
            }
        }
/*         if($req['pay_method']>1){
            $req['pay_method']+=1;
        } */
        if (!in_array($req['pay_method'], $project['support_pay_methods'])) {
            return out(null, 10001, '不支持该支付方式');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,single_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method')->where('id', $req['project_id'])->lock(true)->append(['all_total_buy_num'])->find()->toArray();

            $pay_amount = round($project['single_amount']*$req['buy_num'], 2);
            $pay_integral = 0;

            if ($req['pay_method'] == 1 && $pay_amount >  $user['balance']) {
                exit_out(null, 10002, '余额不足');
            }
            if ($req['pay_method'] == 5) {
                $pay_integral = $project['single_amount'] * $req['buy_num'];
                if ($pay_integral > $user['team_bonus_balance']) {
                    exit_out(null, 10003, '团队奖励不足');
                }
            }

            if (in_array($req['pay_method'], [2,3,4,6])) {
                $type = $req['pay_method'] - 1;
                if ($req['pay_method'] == 6) {
                    $type = 4;
                }
                $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $pay_amount);
            }

/*             if ($project['progress_switch'] == 1 && ($req['buy_num'] + $project['all_total_buy_num'] > $project['total_num'])) {
                exit_out(null, 10004, '超过了项目最大所需份数');
            } */

            if (isset(config('map.order')['pay_method_map'][$req['pay_method']]) === false) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            if (empty($req['pay_method'])) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            $order_sn = build_order_sn($user['id']);

            // 创建订单
            //if($project['class']==1){
                $project['sum_amount'] = round($project['sum_amount']*$req['buy_num'], 2);
            //}

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = $req['buy_num'];
            $project['pay_method'] = $req['pay_method'];
            //$project['equity_certificate_no'] = 'ZX'.mt_rand(1000000000, 9999999999);
            //$project['daily_bonus_ratio'] = round($project['daily_bonus_ratio']*$project['bonus_multiple'], 2);
            //$project['monthly_bonus_ratio'] = round($project['monthly_bonus_ratio']*$project['bonus_multiple'], 2);

            //$project['single_gift_equity'] = round($project['single_gift_equity']*$req['buy_num']*$project['bonus_multiple'], 2);
            $project['single_gift_digital_yuan'] = round($project['single_gift_digital_yuan']*$req['buy_num'], 2);
            $project['sum_amount'] = round($project['sum_amount']*$req['buy_num'], 2);
            $project['price'] = $pay_amount;

            $order = Order::create($project);

            if ($req['pay_method']==1) {
                // 扣余额
                User::changeInc($user['id'],-$pay_amount,'balance',3,$order['id'],1);
                // 累计总收益和赠送数字人民币  到期结算
                // 订单支付完成
                Order::orderPayComplete($order['id']);
            }else if($req['pay_method']==5){
                // 扣团队奖励
                User::changeInc($user['id'],-$pay_amount,'team_bonus_balance',3,$order['id'],2);
                // 累计总收益和赠送数字人民币  到期结算
                // 订单支付完成
                Order::orderPayComplete($order['id']);
            }
            // 发起第三方支付
            if (in_array($req['pay_method'], [2,3,4,6])) {
                $card_info = '';
                if (!empty($paymentConf['card_info'])) {
                    $card_info = json_encode($paymentConf['card_info']);
                    if (empty($card_info)) {
                        $card_info = '';
                    }
                }
                // 创建支付记录
                Payment::create([
                    'user_id' => $user['id'],
                    'trade_sn' => $order_sn,
                    'pay_amount' => $pay_amount,
                    'order_id' => $order['id'],
                    'payment_config_id' => $paymentConf['id'],
                    'channel' => $paymentConf['channel'],
                    'mark' => $paymentConf['mark'],
                    'type' => $paymentConf['type'],
                    'card_info' => $card_info,
                    'product_type'=>1,
                    'pay_voucher_img_url'=>$req['pay_voucher_img_url'],
                ]);
                // 发起支付
                if ($paymentConf['channel'] == 1) {
                    $ret = Payment::requestPayment($order_sn, $paymentConf['mark'], $pay_amount);
                }
                elseif ($paymentConf['channel'] == 2) {
                    $ret = Payment::requestPayment2($order_sn, $paymentConf['mark'], $pay_amount);
                }
                elseif ($paymentConf['channel'] == 3) {
                    $ret = Payment::requestPayment3($order_sn, $paymentConf['mark'], $pay_amount);
                }else if($paymentConf['channel']==8){
                    $ret = Payment::requestPayment4($order_sn, $paymentConf['mark'], $pay_amount);
                }else if($paymentConf['channel']==9){
                    $ret = Payment::requestPayment5($order_sn, $paymentConf['mark'], $pay_amount);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }
    
    // public function ssss(){
    //     $data = Order::alias('o')->join('mp_project p','o.project_id = p.id')->field('o.*,p.sum_amount as psum,p.single_gift_equity as pequity,p.single_gift_digital_yuan as pyuan,p.daily_bonus_ratio as pratio')->where('o.buy_num','>',1)->where('p.class',2)->select()->toArray();
    //     $a = '';
    //     if(empty($data)){
    //         return '没有执行数据';
    //     }else{
    //         foreach($data as $v){
    //             $up = [];
    //             if(($v['buy_num']*$v['psum']) > $v['sum_amount']){
    //                 $up['sum_amount'] =  $v['buy_num']*$v['psum'];
    //             }
    //             if(($v['buy_num']*$v['pequity']) > $v['single_gift_equity']){
    //                 $up['single_gift_equity'] =  $v['buy_num']*$v['pequity'];
    //             }
    //             if(($v['buy_num']*$v['pyuan']) > $v['single_gift_digital_yuan']){
    //                 $up['single_gift_digital_yuan'] =  $v['buy_num']*$v['pyuan'];
    //             }
    //             if(($v['buy_num']*$v['pratio']) > $v['daily_bonus_ratio']){
    //                 $up['daily_bonus_ratio'] =  $v['buy_num']*$v['pratio'];
    //             }
    //             Order::where('id',$v['id'])->update($up);
    //             $a .= 'id:'.$v['id'].'<br>'.'总补贴:'.$v['buy_num']*$v['psum'].'<br>'.'赠送股权:'.$v['buy_num']*$v['pequity'].'<br>'.'赠送期权:'. $v['buy_num']*$v['pyuan'].'<br>'.'分红:'.$v['buy_num']*$v['pratio'].'_______________<br>';
    //         }
    //     }
    //     return $a;
    // }

    public function submitPayVoucher()
    {
        $req = $this->validate(request(), [
            'pay_voucher_img_url|支付凭证' => 'require|url',
            'order_id' => 'require|number'
        ]);
        $user = $this->user;

        if (!Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->count()) {
            return out(null, 10001, '订单不存在');
        }
        $remark = null!=request()->param('remark')?request()->param('remark'):'';
        $upData = [
            'pay_voucher_img_url' => $req['pay_voucher_img_url'],
            'agent_name'=>$remark,
        ];
        Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->update($upData);

        return out();
    }

    public function orderList()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'project_group_id' => 'number',
        ]);
        $user = $this->user;

        $page = request()->param('page', 1);
        $per_page = 10;

        $builder = Db::name('order')->field(
            'id,user_id,order_sn,status,buy_num,
            project_id,project_name,cover_img,days,shengyu,
            shengyu_butie,pay_time,end_time,created_at,price,
            project_group_id,buy_amount,card_process,shouyi
            ')
            ->unionAll("SELECT 
            id,user_id,trade_sn as order_sn,NULL as status,num as buy_num,
            product_id as project_id,name as project_name,NULL as cover_img,NULL as days,team_bonus_balance as shengyu,
            NULL as shengyu_butie,NULL as pay_time,end_time,created_at,single_amount as price,
            NULL as project_group_id,single_amount as buy_amount,NULL as card_process,give_amount as shouyi from mp_user_product WHERE user_id = ".$user['id'])
            ->where('user_id', $user['id']);
        
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['project_group_id'])) {
            $builder->where('project_group_id', $req['project_group_id']);
        }
        $builder->order('created_at', 'desc');
        $builder->limit(($page - 1) * $per_page, $per_page);
        $list = $builder->select()->toArray();
        $count1 = count($list);
        $count1 += Db::name('user_product')->where('user_id', $user['id'])->count();



        // $data = $builder->order('id', 'desc')->paginate(10,false,['query'=>request()->param()]);

        $data['list'] = ['data' => $list, 'last_page' => ceil(bcdiv($count1, $per_page,2)) + 1];

        return out(['data' => $data]);
    }
    
    public function jijin_img()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'sign_img_url|签名凭证' => 'require|url',
        ]);

        $user = $this->user;

        $order = Order::where('id', $req['id'])->find();
        if(!$order) {
            exit_out(null, 10001, '订单不存在');
        }
        if($order['is_confirm'] == 1) {
            exit_out(null, 10001, '您的订单已经确认，无法修改签名');
        }

        $res = Order::where('id', $req['id'])->update(['sign_img_url' => $req['sign_img_url']]);
        return out();
    }

    public function jijin_confirm()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);

        $user = $this->user;

        $order = Order::where('id', $req['id'])->find();
        if(!$order) {
            exit_out(null, 10001, '订单不存在');
        }

        $res = Order::where('id', $req['id'])->update(['is_confirm' => 1]);
        return out();
    }

    public function orderList_bak()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('sum_amount',0);
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['search_type'])) {
            if ($req['search_type'] == 1) {
                $builder->where('single_gift_equity', '>', 0);
            }
            if ($req['search_type'] == 2) {
                $builder->where('single_gift_digital_yuan', '>', 0);
            }
        }
        $data = $builder->order('id', 'desc')->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->paginate(10,false,['query'=>request()->param()])->each(function($item, $key){
            $item['p_id'] = PassiveIncomeRecord::where('order_id',$item['id'])->order('id','desc')->value('id');
            $cre = intval((time()-strtotime($item['created_at'])) / 60 / 60 / 24);
            if($cre >= 77){
                $item['back_amount'] = 1;
            }else{
                $item['back_amount'] = 0;
            }
            return $item;
        });

        return out($data);
    }

    public function ordersList2()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('sum_amount','<>',0);
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['search_type'])) {
            if ($req['search_type'] == 1) {
                $builder->where('single_gift_equity', '>', 0);
            }
            if ($req['search_type'] == 2) {
                $builder->where('single_gift_digital_yuan', '>', 0);
            }
        }
        $data = $builder->order('id', 'desc')->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->paginate(10,false,['query'=>request()->param()])->each(function($item, $key){
            $item['p_id'] = PassiveIncomeRecord::where('order_id',$item['id'])->order('id','desc')->value('id');
            $cre = intval((time()-strtotime($item['created_at'])) / 60 / 60 / 24);
            if($cre >= 77){
                $item['back_amount'] = 1;//显示反还本金
            }else{
                $item['back_amount'] = 0;//不显示反还本金
            }
            return $item;
        });

        return out($data);
    }

    public function ordersList(){
        $user = $this->user;
        $userModel = new User();
        $data = [];
        $data['profiting_bonus'] = $userModel->getProfitingBonusAttr(0,$user);
        $list = Order::where('user_id', $user['id'])->where('status', '>', 1)->field('id,cover_img,single_amount,buy_num,project_name,sum_amount,sum_amount2,order_sn,daily_bonus_ratio,dividend_cycle,period,created_at')->order('created_at','desc')->paginate(5)->each(function($item,$key){
            if($item['sum_amount']==0 && $item['sum_amount2']>0){
                //$item['sum_amount'] = bcmul($item['daily_bonus_ratio']*config('config.passive_income_days_conf')[$item['period']]/100,2);
                $item['sum_amount'] = $item['sum_amount2'];
            }
            $daily_bonus = bcmul($item['single_amount'],$item['daily_bonus_ratio']/100,2);
           
            $daily_bonus = bcmul($daily_bonus,$item['buy_num'],2);
            $item['price'] = bcmul($item['single_amount'],$item['buy_num'],2);
        if($item['dividend_cycle'] == '1 month'){
                $day_remark = '每月';
            }else{
                $day_remark = '每日';
            }
            
            $item['text'] = "单笔认购{$item['price']}元，{$day_remark}收益{$daily_bonus}元，永久性收益！";

            return $item;
        });
        $data['list'] = $list;
        return out($data);
    }

    public function investmentList(){
        $user = $this->user;
        $list = Order::where('user_id', $user['id'])->where('status', '>', 1)->where('is_gift',0)->field('id,project_id,single_amount,buy_num,project_name,sum_amount,sum_amount2,order_sn,daily_bonus_ratio,period,created_at')->order('created_at','desc')->paginate(20)->each(function($item,$key){
            $bonusMultiple = Project::where('id',$item['project_id'])->value('bonus_multiple');
            $item['bonus_multiple'] = $bonusMultiple;
            $item['price'] = bcmul($item['single_amount'],$item['buy_num'],2);
            $item['text'] = "{$item['price']}元投资{$bonusMultiple}倍{$item['project_name']}";
            

            return $item;
        });
        $data['list'] = $list;
        return out($data);
    }

    public function orderDetail()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        $data = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->find();
        $data['card_info'] = null;
        if (!empty($data)) {
            $payment = Payment::field('card_info')->where('order_id', $req['order_id'])->find();
            $data['card_info'] = $payment['card_info'];
        }

        return out($data);
    }

    public function saleOrder()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $order = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->lock(true)->find();
            if (empty($order)) {
                exit_out(null, 10001, '订单不存在');
            }
            if ($order['status'] != 3) {
                exit_out(null, 10001, '订单状态异常，不能出售');
            }

            Order::where('id', $req['order_id'])->update(['status' => 4, 'sale_time' => time()]);

            User::changeBalance($user['id'], $order['gain_bonus'], 6, $req['order_id']);

            // 检查返回本金 签到累计满77天才会返还80%的本金，注意积分兑换的不返还本金
            if ($order['pay_method'] != 5) {
                $signin_num = UserSignin::where('user_id', $user['id'])->count();
                if ($signin_num >= 77) {
                //if ($signin_num >= 3) {
                    $change_amount = round($order['buy_amount']*0.8, 2);
                    User::changeBalance($user['id'], $change_amount, 12, $req['order_id']);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
    
    public function takeDividend(){
        $user = $this->user;
  
        $isStatus = Order::where('user_id', $user['id'])->where('project_name','2022年年底分红')->find();

        if($isStatus){
            return out(null, 10001, '您已领取2022年年底分红');
        }else{
            $arr = User::where('id', $user['id'])->append(['equity'])->find()->toArray();

            $order_sn = build_order_sn($user['id']);
            // 创建分红订单
            $project['project_name'] = '2022年年底分红';
            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 0;
            $project['pay_method'] = 0;//gain_bonus
            $project['gain_bonus'] = $arr['equity']*10;//
            $project['status'] = 2;//
            $project['equity_certificate_no'] = 'ZX'.mt_rand(1000000000, 9999999999);
            $project['daily_bonus_ratio'] = 0;
            $project['sum_amount'] = 2400;
            $project['single_gift_equity'] = 0;
            $project['single_gift_digital_yuan'] = 0;
            Order::create($project);
        }
        return out();
    }

    public function takeDividendstate(){
        $user = $this->user;
        $isStatus = Order::where('user_id', $user['id'])->where('project_name','2022年年底分红')->find();

        if($isStatus){
            $data['take_status']=0;
        }else{
            $arr = User::where('id', $user['id'])->append(['equity'])->find()->toArray();
            if($arr['equity']<1){
                $data['take_status']=0;
            }else{
                $data['take_status']=1;
            }
        }
        return out($data);
    }

    //头像
    public function getUserImg()
    {
        $user = $this->user;
        $count = Order::where('user_id', $user['id'])->where('project_name','高级用户')->count();
        if ($user['id'] == 212884){
            $count = 1;
        }
        return out($count);
    }
}
