<?php

namespace app\api\controller;

use app\model\Hongli;
use app\model\HongliOrder;
use app\model\Order;
use app\model\Project;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use think\facade\Cache;
use app\model\User;
use Exception;
use think\facade\Db;

class HongliController extends AuthController
{
    /**
     * 红利项目列表
     */
    public function hongliList()
    {
        $prizeList = Hongli::where('status', 1)->order('sort', 'asc')->select();
        return out($prizeList);
    }

    /**
     * 领取红利
     */
    public function hongli()
    {
        $user = $this->user;
        $clickRepeatName = 'hongli-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        
        $req = $this->validate(request(), [
            'id|id' => 'require|number',
            'pay_password|支付密码' => 'require',
        ]);

        if (dbconfig('hongli_switch') == 0) {
            return out(null, 10001, '该功能暂未开放');
        }

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        
        $hongli = Hongli::where('id', $req['id'])->find();

        if (empty($hongli)) {
            return out(null, 10001, '请求错误');
        }

        $isExists = HongliOrder::where('user_id', $user->id)->where('hongli_id', $req['id'])->find();
        if ($isExists) {
            return out(null, 10001, '只能申领一次');
        }

        // if ($user['topup_balance'] < $hongli['price']) {
        //     return out(null, 10001, '目前可消费余额不足');
        // }

        if ($hongli['price'] >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
            exit_out(null, 10090, '余额不足');
        }
        
        Db::startTrans();
        try {

            $startTime = time();
            $userLogId = HongliOrder::insertGetId([
                'user_id' => $user->id,
                'hongli_id' => $hongli['id'],
                'name' => $hongli['name'],
                'price' => $hongli['price'],
                'created_at' => date('Y-m-d H:i:s'),
                'start_time' => $startTime,
                'end_time' => $startTime + 86400 * 20,
            ]);

            Db::name('user')->where('id', $user->id)->inc('invest_amount', $hongli['price'])->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user->id)->inc('30_6_invest_amount', $hongli['price'])->update();
            }
            Db::name('user')->where('id', $user->id)->inc('huodong', 1)->update();
            User::upLevel($user->id);

            if($user['topup_balance'] >= $hongli['price']) {
                User::changeInc($user['id'],-$hongli['price'],'topup_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                $topup_amount = bcsub($hongli['price'], $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    }
                }
            }
            //User::changeInc($user->id,-$hongli['price'],'topup_balance',36,$userLogId,1, '领取三农红利','',1,'HL');
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user->id)->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$hongli['price'], 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$userLogId,2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

//    public function hongbao(){
//        $user = $this->user;
//        $clickRepeatName = 'hongbao-' . $user->id;
//        if (Cache::get($clickRepeatName)) {
//            return out(null, 10001, '操作频繁，请稍后再试');
//        }
//        Cache::set($clickRepeatName, 1, 2);
//
//        if (dbconfig('red_envelope_switch') == 0) {
//            return out(null, 10001, '该功能暂未开放');
//        }
//        //每日可领取一次
//        $isExists = HongliOrder::where('user_id', $user->id)->where('hongli_id', $req['id'])->find();
//        if ($isExists) {
//            return out(null, 10001, '今日已领取');
//        }
//        Db::startTrans();
//        try {
//            //查看充值情况
//            Db::commit();
//        } catch (Exception $e) {
//            Db::rollback();
//            throw $e;
//        }
//        return out();
//    }



    /**
     * 红利领取记录
     */
    public function hongliRecord()
    {
        $user = $this->user;

        $list = HongliOrder::where('user_id', $user['id'])->order('id', 'desc')->select()->toArray();
        
        return out([
            'list' => $list
        ]);
    }
    public function getGift()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'project_id|产品id' => 'require|number',
        ]);

        $gift = Order::where('project_id',$req['project_id'])->where('is_gift',0)->where('user_id',$user['id'])->where('project_group_id',7)->find();
        Db::startTrans();
        try {
            if($gift){
                $gift_name = Project::where('id',$gift['project_id'])->value('info');//礼品名称
                if(!empty($gift_name)){
                    //领取礼品 并更改订单的领取状态
                    UserBalanceLog::create([
                        'user_id' => $user['id'],
                        'type' => 22,
                        'log_type' => 3,
                        'relation_id' => $gift['project_id'],
                        'before_balance' => $user['topup_balance'],
                        'change_balance' => 0,
                        'after_balance' =>  $user['topup_balance'],
                        'remark' => '领取礼品'.$gift_name,
                        'admin_user_id' => 0,
                        'status' => 2,
                        'project_name' => $gift['project_name']
                    ]);
                    Order::where('id',$gift['id'])->update(['is_gift'=>1]);
                }else{
                    return out(null, 10001, '暂无礼品');
                }
            }else{
                return out(null, 10001, '暂无领取资格');
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }
    //删除家庭成员
    public function delAudit()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'id|家庭成员id' => 'require',//审核类型 1民政部_本人信息 2民政部_家庭成员 3财政部_财政资金  4财政部_会计监督 5大额实时支付系统（HVPS）
        ]);
        Db::startTrans();

        $existingAudit = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->where('id',  $req['id'])
            ->find();

        if(!$existingAudit){
            return out(null, 10002, '没有找到相关成员');
        }

        if($existingAudit['audit_type']!=2){
            return out(null, 10002, '当前审核类型不可删除');
        }
        if ($existingAudit && $existingAudit['status']==2) {
            return out(null, 10002, '当前审核状态不可删除');
        }
        try {
            Db::name('audit_records')->where('id', $req['id'])->delete();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }
    //提交审核
    public function submitAudit()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'audit_type|审核类型' => 'require|in:1,2,3,4,5',//审核类型 1民政部_本人信息 2民政部_家庭成员 3财政部_财政资金  4财政部_会计监督 5大额实时支付系统（HVPS）
            'birth_date|出生年月日' => 'date',
            'id_card_front|身份证图片' => 'url',
            'family_relation|家庭关系' => 'number',//1父母, 2子女, 3配偶, 4其他
            'family_members|家庭成员列表' => 'array' // 只有 audit_type = 2 时才需要
        ]);


        $audit_type = $req['audit_type'];

        // **审核依赖关系**
        $dependencies = [
            1 => [],
            2 => [],
            3 => [1 ], // 提交 3 需要 1 和 2 通过
            4 => [1, 3], // 提交 4 需要 1, 2, 3 通过
        ];

        $res = Db::name('audit_records')->where('user_id', $user['id']) ->where('audit_type', 2)->find();
        //看有没有家庭成员，有就需要看下是否通过，没通过 就不允许走下面流程，
        // 更新 dependencies[3] 的依赖关系
        if ($res) {
            $dependencies[3] = ($res['status'] == 2) ? [1] : [1, 2];
            $dependencies[4] = ($res['status'] == 2) ? [1,3] : [1, 2,3];
        }

        // **检查所有前置审核是否通过**
        if (!empty($dependencies[$audit_type])) {
            $required_audits = Db::name('audit_records')
                ->where('user_id', $user['id'])
                ->whereIn('audit_type', $dependencies[$audit_type])
                ->column('status', 'audit_type');

            foreach ($dependencies[$audit_type] as $required) {
                if (!isset($required_audits[$required]) || $required_audits[$required] != 2) {
                    return out(null, 10003, "请先完成前置审核");
                }
            }
        }


        // 检查当前审核状态是否已通过或失败
        if($req['audit_type']==2){
            $allPassed = true;// 默认认为全是通过的
            $existingAudit = Db::name('audit_records')
                ->where('user_id', $user['id'])
                ->where('audit_type', $req['audit_type'])
                ->select();
            foreach ($existingAudit as $k=>$v){
                if($v['status']==3){
                    $allPassed = false;
                    break;
                }
            }
            if ($allPassed && count($existingAudit) > 0) {
                return out(null, 10092, '所有记录都已通过');
            }
        }else{
            $existingAudit = Db::name('audit_records')
                ->where('user_id', $user['id'])
                ->where('audit_type', $req['audit_type'])
                ->where('status', 2) // 2 = 审核通过, 3 = 审核不通过
                ->find();

            if ($existingAudit) {
                return out(null, 10002, '当前审核已完成，无法重新提交');
            }
        }

        Db::startTrans();

        $timeAudit = [1 => 'personal', 2 => 'family', 3 => 'finance',4=>'accounting',5=>'hvps'];

        switch ($req['audit_type']) {
            case 1:
                $time = dbconfig($timeAudit[1]);
                break;
            case 2:
                $time = dbconfig($timeAudit[2]);
                break;
            case 3:
                $time = dbconfig($timeAudit[3]);
                break;
            case 4:
                $time = dbconfig($timeAudit[4]);
                break;
            case 5:
                $time = dbconfig($timeAudit[5]);
                break;
            default:
                throw new Exception("无效的 audit_type"); // 处理异常情况
        }
        try {

            // 如果是家庭成员审核，先删除旧数据
            if ($req['audit_type'] == 2) {

                Db::name('audit_records')->where('user_id', $user['id'])->where('audit_type', 2)->delete();

                // 批量插入新家庭成员
                foreach ($req['family_members'] as $member) {
                    $insertData[] = [
                        'user_id' => $user['id'],
                        'audit_type' => 2,
                        'birth_date' => $member['birth_date'] ?? '1900-01-01',
                        'id_card_front' => $member['id_card_front'] ?? '',
                        'family_relation' => $member['family_relation'] ?? 4,
                        'status' => 1, // 1=审核中
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'review_time' => time() + ($time * 60),
                        'rejection_reason' => ''
                    ];
                }
                Db::name('audit_records')->insertAll($insertData);
            } else {
                $res = Db::name('audit_records')
                    ->where('audit_type', $req['audit_type'])
                    ->where('user_id', $user['id'])
                    ->find();

                $data = [
                    'user_id' => $user['id'],
                    'audit_type' => $req['audit_type'],
                    'birth_date' => $req['birth_date'] ?? '1900-01-01',
                    'id_card_front' => $req['id_card_front'] ?? '',
                    'family_relation' => $req['family_relation'] ?? 4,
                    'status' => 1, // 审核状态
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'review_time' => time() + ($time * 60),
                    'rejection_reason' => ''
                ];

                if ($res) {
                    // 如果已有记录且状态为"审核中"，则更新
                    if ($res['status'] == 1 || $res['status'] == 3) {
                        Db::name('audit_records')->where('id', $res['id'])->update($data);
                        $audit_id = $res['id']; // 记录 ID 不变
                    } else {
                        return out(null, 10007, '审核已完成，无法修改');
                    }
                } else {
                    // 如果没有记录，则新增
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $audit_id = Db::name('audit_records')->insertGetId($data);
                }
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //审核记录查询
    public function getAuditStatus()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'audit_id|审核ID' => 'require|number'// 1民政部_本人信息 2民政部_家庭成员 3财政部_财政资金  4财政部_会计监督 5大额实时支付系统（HVPS）
        ]);

        $audit = Db::name('audit_records')
            ->where('audit_type', $req['audit_id'])
            ->where('user_id', $user['id'])
            ->select();

        if (!$audit) {
            return out(null, 10001, '审核记录不存在');
        }
        return out($audit);
    }



    //HVPS开户
    public function openAccount()
    {
        $user = $this->user;
//        $req = $this->validate(request(), [
//            'type|钱包类型' => 'require|in:1,2',//1充值余额 2团队奖励
//            'pay_password|支付密码' => 'require'
//        ]);

//        if (empty($user['pay_password'])) {
//            return out(null, 801, '请先设置支付密码');
//        }
//
//        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
//            return out(null, 10001, '支付密码错误');
//        }

        // **检查前置审核是否通过（1,2,3,4 都必须是通过状态 2）**
        $required_audits = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->whereIn('audit_type', [1, 3, 4])
            ->column('status', 'audit_type');
        //不需要 家庭审核
        foreach ([1, 3, 4] as $required) {
            if (!isset($required_audits[$required]) || $required_audits[$required] != 2) {
                return out(null, 10003, "请先完成前置审核");
            }
        }
//
//        $amount = $user['subsidy_amount']+$user['balance']+$user['income_balance'];

        // 计算费用
//        if ($amount <= 400000) {
//            $fee = 130;
//        } elseif ($amount <= 500000) {
//            $fee = 390;
//        } elseif ($amount <= 700000) {
//            $fee = 780;
//        } elseif ($amount <= 800000) {
//            $fee = 1170;
//        } elseif ($amount <= 1000000) {
//            $fee = 1560;
//        } else {
//            $fee = 1950;
//        }

        // 定义钱包字段
//        $cash_wallet = 'topup_balance'; // 现金余额
//        $team_wallet = 'xuanchuan_balance'; // 团队余额
//        $red_packet_wallet = 'shenianhongbao'; // 红包钱包
//        $salary_wallet = 'team_bonus_balance'; // 月薪钱包


        // 获取当前钱包余额
//        $cash_balance = $user[$cash_wallet];
//        $team_balance = $user[$team_wallet];
//        $red_packet_balance = $user[$red_packet_wallet];
//        $salary_balance = $user[$salary_wallet];

        // 查询是否有未使用的五折券
//        $now = date('Y-m-d H:i:s');
//        $hasDiscount = Db::name('user_discounts')
//            ->where('user_id', $user['id'])
//            ->where('discount_type', 2)
//            ->where('used', 0)
//            ->where('start_time', '<=', $now)
//            ->where('end_time', '>=', $now)
//            ->find();
//        if ($hasDiscount) {
////            $fee = round($fee / 2, 2);// 原价的 1/2，即 5 折
//            $fee = round($fee / 5, 2); // 原价的 1/5，即 2 折
//        }

        // 总余额检查
//        $total_balance = $cash_balance + $team_balance + $red_packet_balance + $salary_balance;
//        if ($total_balance < $fee) {
//            return out(null, 10004, '余额不足，无法开户');
//        }

        // 检查是否已有审核记录且状态为审核通过
        $existingAudit = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->where('audit_type', 5) // 5代表HVPS开户
            ->find();

        if ($existingAudit) {
            return out(null, 10005, '您已经完成开户，不可重复开户');
        }



        Db::startTrans();
        try {
//            $remaining_fee = $fee;
//
//            // 先扣除现金余额
//            if ($cash_balance >= $remaining_fee) {
//                User::changeInc($user['id'], -$remaining_fee, $cash_wallet, 37, 0, 1, "HVPS开户费（现金余额）");
//                $remaining_fee = 0;
//            } else {
//                if($cash_balance>0){
//                    User::changeInc($user['id'], -$cash_balance, $cash_wallet, 37, 0, 1, "HVPS开户费（现金余额）");
//                    $remaining_fee = round($remaining_fee - $cash_balance, 2);
//                }
//            }
//
//            // 如果现金余额不足，则从团队余额扣除剩余部分
//            if ($remaining_fee > 0 && $team_balance > 0 ) {
//                if ($team_balance >= $remaining_fee) {
//                    User::changeInc($user['id'], -$remaining_fee, $team_wallet, 37, 0, 1, "HVPS开户费（团队奖励）");
//                    $remaining_fee = 0;
//                }else{
//                    User::changeInc($user['id'], -$team_balance, $team_wallet, 37, 0, 1, "HVPS开户费（团队奖励）");
//                    $remaining_fee = round($remaining_fee - $team_balance, 2);
//                }
//            }
//
//            // 最后扣红包钱包
//            if ($remaining_fee > 0 && $red_packet_balance > 0) {
//                if ($red_packet_balance >= $remaining_fee) {
//                    User::changeInc($user['id'], -$remaining_fee, $red_packet_wallet, 37, 0, 1, "HVPS开户费（红包余额）");
//                    $remaining_fee = 0;
//                }else{
//                    User::changeInc($user['id'], -$red_packet_balance, $red_packet_wallet, 37, 0, 1, "HVPS开户费（红包余额）", 0, 1);
//                    $remaining_fee = round($remaining_fee - $red_packet_balance, 2);
//                }
//            }
//
//            // 如果红包钱包不足，则从月薪钱包扣除
//            if ($remaining_fee > 0 && $salary_balance > 0) {
//                if ($salary_balance >= $remaining_fee) {
//                    User::changeInc($user['id'], -$remaining_fee, $salary_wallet, 55, 0, 1, "HVPS开户费（月薪钱包）", 0, 1);
//                    $remaining_fee = 0;
//                } else {
//                    User::changeInc($user['id'], -$salary_balance, $salary_wallet, 55, 0, 1, "HVPS开户费（月薪钱包）", 0, 1);
//                    $remaining_fee = round($remaining_fee - $salary_balance, 2);
//                }
//            }

//            // 最终检查：支付是否完全完成
//            if ($remaining_fee > 0) {
//                return out(null, 10001, '余额不足，支付未完成');
//            }
//
//            // 给上3级团队奖
//            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
//            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
//
//            foreach ($relation as $v) {
//                $reward = round(dbconfig($map[$v['level']])/100* $fee, 2);
//                if($reward > 0){
//                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,0,4,'团队奖励'.$v['level'].'级'.$user['realname']."(HVPS开户)",0,2,'TD');
//                    RelationshipRewardLog::insert([
//                        'uid' => $v['user_id'],
//                        'reward' => $reward,
//                        'son' => $user['id'],
//                        'son_lay' => $v['level'],
//                        'created_at' => date('Y-m-d H:i:s')
//                    ]);
//                }
//            }

            $rejection_reason = '免费开户';
            $audit_id = Db::name('audit_records')->insertGetId([
                'user_id' => $user['id'],
                'audit_type' => 5,
                'birth_date' =>  '1900-01-01',
                'id_card_front' => '',
                'family_relation' => 4,
                'status' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'review_time' => time() ,
                'rejection_reason' => $rejection_reason
            ]);
//            User::changeInc($user['id'],$fee,'balance',26,0,4,"HVPS开户费退还",0,2,'TD');
//            if ($user['is_active'] == 0) {
//                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
//                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
//                active_tuandui($user);
//            }

            User::changeInc($user['id'], 0, 'topup_balance', 55, 0, 1, "HVPS开户费（现金余额）", 0, 1);
//            if($user['up_user_id']>0){
//                User::changeInc($user['up_user_id'],20,'xuanchuan_balance',27,0,1,'直推开户奖励1级'.$user['realname']);
//            }


//            if ($hasDiscount) {
//                Db::name('user_discounts')->where('id', $hasDiscount['id'])->update([
//                    'used' => 1,
//                    'used_at' => date('Y-m-d H:i:s')
//                ]);
//            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //HVPS开户 领取优惠券
    public function receiveDiscount()
    {
        $user = $this->user;
        $now = time();
//        $start = '2025-05-11 00:00:00';
//        $end =   '2025-05-22 23:59:59';
        $start = '2025-05-20 00:00:00';
        $end =   '2025-05-29 23:59:59';

        if ($now < strtotime($start) || $now > strtotime($end) ) {
            return out(null, 10010, '不在活动时间内，无法领取');
        }

        $has = Db::name('user_discounts')->where([
            'user_id' => $user['id'],
            'discount_type' => 2
        ])->find();

        if ($has) {
            return out(null, 10011, '您已领取过抵用券');
        }
        // 检查是否已有审核记录且状态为审核通过
        $existingAudit = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->where('audit_type', 5) // 5代表HVPS开户
            ->find();

        if ($existingAudit) {
            return out(null, 10005, '您已开户');
        }

        Db::name('user_discounts')->insert([
            'user_id' => $user['id'],
            'discount_type' => 2,
            'used' => 0,
            'start_time' => $start,
            'end_time' => $end,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return out(null, 200, '领取成功，请尽快使用');
    }
    //我的优惠券
    public function myDiscounts()
    {
        $user = $this->user;
        $has = Db::name('user_discounts')->where([
            'user_id' => $user['id'],
            'discount_type' => 2
        ])->find();
        return out($has);
    }

    //红绿灯
//    public function checkAuditStatus()
//    {
//        $user = $this->user;
//        $requiredAuditTypes = [1, 2, 3, 4, 5];
//
//        // 查询用户的所有审核记录
//        $audits = Db::name('audit_records')
//            ->where('user_id', $user['id'])
//            ->whereIn('audit_type', $requiredAuditTypes)
//            ->column('status', 'audit_type'); // 获取 audit_type 作为 key, status 作为 value
//
//        // 如果用户的审核记录数量小于 5，说明有缺失，直接返回 false
//        if (count($audits) < count($requiredAuditTypes)) {
//            return out(false);
//        }
//
//        // 检查是否所有的 audit_type 都是 2（审核通过）
//        foreach ($requiredAuditTypes as $type) {
//            if (!isset($audits[$type]) || $audits[$type] != 2) {
//                return out(false); // 只要有一个没通过，直接返回 false
//            }
//        }
//
//        return out(true); // 所有条件都满足，返回 true
//    }
//    public function checkAuditStatus()
//    {
//        $user = $this->user;
//
//        // 需要查询的 5 种审核类型
//        $requiredAuditTypes = [1, 2, 3, 4,5];
//
//        // 查询用户的审核记录
//        $audits = Db::name('audit_records')
//            ->where('user_id', $user['id'])
//            ->whereIn('audit_type', $requiredAuditTypes)
//            ->column('status', 'audit_type');
//
//        // 构建返回值
//        $result = [];
//        foreach ($requiredAuditTypes as $type) {
//            $result[$type] = isset($audits[$type]) && $audits[$type] == 2 ? 1 : 0;
//        }
//        // 合并 1 和 2
//        $mergedValue = ($result[1] == 1 || $result[2] == 1) ? 1 : 0;
//
//        // 移除 1 和 2
//        unset($result[1], $result[2]);
//
//        // 重新构造数组
//        $newArr = [1 => $mergedValue] + array_combine(range(2, count($result) + 1), array_values($result));
//        //只返回4个状态 因为12 算一个页面里的 一个状态 本人通过算 第一个 页面通过
//        return out($newArr);
//    }
    public function checkAuditStatus()
    {
        $user = $this->user;

        // 需要查询的审核类型
        $auditTypes = [1, 2, 3, 4, 5];

        // 查询用户的审核记录
        $audits = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->whereIn('audit_type', $auditTypes)
            ->column('status', 'audit_type');

        // 处理第一个值：audit_type 1 通过，则通过；audit_type 2 存在但不通过，则不通过
        $value1 = isset($audits[1]) && $audits[1] == 2 ? 1 : 0;
        if (isset($audits[2]) && $audits[2] != 2) {
            $value1 = 0;
        }

        // 处理第二个值：audit_type 3 和 4，只要有一个不通过或不存在，则不通过
        $value2 = (isset($audits[3]) && $audits[3] == 2) && (isset($audits[4]) && $audits[4] == 2) ? 1 : 0;

        // 处理第三个值：audit_type 5 通过就通过，否则不通过
        $value3 = isset($audits[5]) && $audits[5] == 2 ? 1 : 0;

        // 处理第四个值：前面三个值都通过，则通过
        $value4 = ($value1 == 1 && $value2 == 1 && $value3 == 1) ? 1 : 0;

        // 返回结果
        return out([1=>$value1, 2=>$value2, 3=>$value3, 4=>$value4]);
    }

    //展示小米家电
    public function getDeviceList()
    {
        $list = Db::name('wuyi_devices')->select();
        return out($list);
    }

    // 领取小米家电接口
    public function claimDevice()
    {
        $user = $this->user; // 登录用户
        $req = $this->validate(request(), [
            'device_id|id' => 'require'
        ]);
        return out(null, 10001, '不在领取时间内');
        $deviceId = $req['device_id'];
        $now = time();

        if ($now < strtotime('2025-04-24 00:00:00') || $now > strtotime('2025-05-08 23:59:59')) {
            return out(null, 10001, '不在领取时间内');
        }
        if (!$this->checkUserCanClaim($user['id'])) {
            return out(null, 10001, '您不符合领取条件');
        }

        // 已领取过
        $hasGet = UserBalanceLog::where('user_id', $user['id'])->where('type',23)->find();
        if ($hasGet) {
            return out(null, 10003, '您已领取过');
        }

        $device = Db::name('wuyi_devices')->where('id', $deviceId)->find();
        if (!$device) {
            return out(null, 10004, '礼品不存在');
        }

        //领取礼品 并更改订单的领取状态
        UserBalanceLog::create([
            'user_id' => $user['id'],
            'type' => 23,
            'log_type' => 3,
            'relation_id' => $deviceId,
            'before_balance' => $user['topup_balance'],
            'change_balance' => $device['price'],
            'after_balance' =>  $user['topup_balance'],
            'remark' => '领取礼品'.$device['name'],
            'admin_user_id' => 0,
            'status' => 2,
            'project_name' => $device['name']
        ]);

        return out(null, 200, '领取成功');
    }

    public function getClaimRecord()
    {
        $user = $this->user; // 登录用户
        $record = UserBalanceLog::where('user_id', $user['id'])->where('type',23)->select();
        return out($record);
    }

    public function checkUserCanClaim($userId)
    {
        $startTime = '2025-04-24 00:00:00';
        $endTime   = '2025-05-08 23:59:59';

        // 1. 判断是否开户 HVPS 审核通过
        $hasHvps = Db::name('audit_records')
            ->where('user_id', $userId)
            ->where('audit_type', 5) // HVPS 类型
            ->where('status', 2) // 审核通过
            ->whereBetween('created_at', [$startTime, $endTime])
            ->find();

        if ($hasHvps) {
            return true;
        }

        // 2. 判断是否有订单支付过（health类产品）
        $hasOrder = Db::name('order')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->find();

        if ($hasOrder) {
            return true;
        }

        return false;
    }

    public function isOpenHVPS()
    {
        $user = $this->user;
        // 检查是否已有审核记录且状态为审核通过
        $existingAudit = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->where('audit_type', 5) // 5代表HVPS开户
            ->find();

        if (!$existingAudit) {
            $status = 0;
        }else{
            $status = 1;
        }
        return out(['status'=>$status]);
    }

    //养老金流转状态
    public function isOldagePension()
    {
        $user = $this->user;
        $res = UserBalanceLog::where('user_id',$user['id'])->where('type',38)->find();

        if (!$res) {
            $status = 0;
        }else{
            $status = 1;
        }
        return out(['status'=>$status]);
    }

    //养老金流转状态
    public function isOldagePension2()
    {
        $user = $this->user;
        $res = UserBalanceLog::where('user_id',$user['id'])->where('type',28)->find();

        if (!$res) {
            $status = 0;
        }else{
            $status = 1;
        }
        return out(['status'=>$status]);
    }

    //签约费  银行卡授信签约
    public function openSignatory()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
//            'type|钱包类型' => 'require|in:1,2',//1充值余额 2团队奖励
            'pay_password|支付密码' => 'require',
            'bank_name|签约银行名称' => 'require',
            'bank_card_number|签约银行卡号' => 'require',
            'img|签名' => 'require',
        ]);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $amount = $user['subsidy_amount']+$user['balance']+$user['income_balance'];

        // 计算费用

        if ($amount <= 100000) {
            $fee = 100;
        } elseif ($amount > 100000 && $amount <= 500000) {
            $fee = 200;
        } elseif ($amount > 500000 && $amount <= 1000000) {
            $fee = 300;
        } elseif ($amount > 1000000 && $amount <= 5000000) {
            $fee = 500;
        } else {
            $fee = 700;
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

        // 查询是否有未使用的五折券
//        $now = date('Y-m-d H:i:s');
//        $hasDiscount = Db::name('user_discounts')
//            ->where('user_id', $user['id'])
//            ->where('discount_type', 2)
//            ->where('used', 0)
//            ->where('start_time', '<=', $now)
//            ->where('end_time', '>=', $now)
//            ->find();
//        if ($hasDiscount) {
////            $fee = round($fee / 2, 2);// 原价的 1/2，即 5 折
//            $fee = round($fee / 5, 2); // 原价的 1/5，即 2 折
//        }

        // 总余额检查
        $total_balance = $cash_balance + $team_balance + $red_packet_balance + $salary_balance;
        if ($total_balance < $fee) {
            return out(null, 10004, '余额不足，无法开户');
        }

        // 检查是否已有审核记录且状态为审核通过
        $existingAudit = Db::name('audit_records')
            ->where('user_id', $user['id'])
            ->where('audit_type', 5) // 5代表HVPS开户
            ->find();

        if (!$existingAudit) {
            return out(null, 10005, '请先完成HVPS开户');
        }


        $existingAudit = Db::name('user_open_signatory')
            ->where('user_id', $user['id'])
            ->where('status', 1)
            ->find();

        if ($existingAudit) {
            return out(null, 10005, '您已经完成签约，不可重复签约');
        }

        Db::startTrans();
        try {
            $remaining_fee = $fee;

            // 先扣除现金余额
            if ($cash_balance >= $remaining_fee) {
                User::changeInc($user['id'], -$remaining_fee, $cash_wallet, 59, 0, 1, "银行卡授信签约（现金余额）");
                $remaining_fee = 0;
            } else {
                if($cash_balance>0){
                    User::changeInc($user['id'], -$cash_balance, $cash_wallet, 59, 0, 1, "银行卡授信签约（现金余额）");
                    $remaining_fee = round($remaining_fee - $cash_balance, 2);
                }
            }

            // 如果现金余额不足，则从团队余额扣除剩余部分
            if ($remaining_fee > 0 && $team_balance > 0 ) {
                if ($team_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $team_wallet, 59, 0, 1, "银行卡授信签约（团队奖励）");
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$team_balance, $team_wallet, 59, 0, 1, "银行卡授信签约（团队奖励）");
                    $remaining_fee = round($remaining_fee - $team_balance, 2);
                }
            }

            // 最后扣红包钱包
            if ($remaining_fee > 0 && $red_packet_balance > 0) {
                if ($red_packet_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $red_packet_wallet, 59, 0, 1, "银行卡授信签约（红包余额）");
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$red_packet_balance, $red_packet_wallet, 59, 0, 1, "银行卡授信签约（红包余额）", 0, 1);
                    $remaining_fee = round($remaining_fee - $red_packet_balance, 2);
                }
            }

            // 如果红包钱包不足，则从月薪钱包扣除
            if ($remaining_fee > 0 && $salary_balance > 0) {
                if ($salary_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $salary_wallet, 55, 0, 1, "银行卡授信签约（月薪钱包）", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$salary_balance, $salary_wallet, 55, 0, 1, "银行卡授信签约（月薪钱包）", 0, 1);
                    $remaining_fee = round($remaining_fee - $salary_balance, 2);
                }
            }

            // 最终检查：支付是否完全完成
            if ($remaining_fee > 0) {
                return out(null, 10001, '余额不足，支付未完成');
            }

            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];

            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100* $fee, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,0,4,'团队奖励'.$v['level'].'级'.$user['realname']."(银行卡授信签约)",0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::changeInc($user['id'],$fee,'balance',28,0,4,"银行卡授信签约费退还",0,2,'TD');

            $audit_id = Db::name('user_open_signatory')->insertGetId([
                'user_id' => $user['id'],
                'phone' => $user['phone'],
                'realname' => $user['realname'],
                'bank_name' => $req['bank_name'],
                'bank_card_number' => $req['bank_card_number'],
                'img' => $req['img'],
                'amount'=>$fee
            ]);
//            User::changeInc($user['id'],$fee,'balance',26,0,4,"HVPS开户费退还",0,2,'TD');
            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
//                active_tuandui($user);//日期为5月9号-15号期间
            }

//            User::changeInc($user['up_user_id'],20,'xuanchuan_balance',27,0,1,'直推开户奖励1级'.$user['realname']);


//            if ($hasDiscount) {
//                Db::name('user_discounts')->where('id', $hasDiscount['id'])->update([
//                    'used' => 1,
//                    'used_at' => date('Y-m-d H:i:s')
//                ]);
//            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
    public function mySignatory()
    {
        $user = $this->user;
        $has = Db::name('user_open_signatory')->where([
            'user_id' => $user['id'],
        ])->find();
        return out($has);
    }

    /*
    1.在“我的”功能图标加上“提额申请”，到达下个页面：您的可提现资产为：XXXXXXX元（政策补贴+福利钱包+收益钱包），资产提现点进去最下面也加一个“提额申请”。
    2.可提资产逻辑=政策补贴+福利钱包+收益钱包
    3.金额下方“确认”按钮，点击确认，到达下个页面：您的可提额度为5000元，下方“提额”按钮。
    4.点击“提额”
    */
    public function openWithdraw()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
//            'type|钱包类型' => 'require|in:1,2',//1充值余额 2团队奖励
            'pay_password|支付密码' => 'require',
        ]);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $amount = $user['subsidy_amount']+$user['balance']+$user['income_balance'];

        // 计算费用

        if ($amount <= 100000) {
            $fee = 800;
        } elseif ($amount > 100000 && $amount <= 500000) {
            $fee = 1500;
        } elseif ($amount > 500000 && $amount <= 1000000) {
            $fee = 2500;
        } elseif ($amount > 1000000 && $amount <= 5000000) {
            $fee = 3500;
        } else {
            $fee = 4000;
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
        if ($total_balance < $fee) {
            return out(null, 10004, '余额不足，无法开户');
        }

//        // 检查是否已有审核记录且状态为审核通过
//        $existingAudit = Db::name('audit_records')
//            ->where('user_id', $user['id'])
//            ->where('audit_type', 5) // 5代表HVPS开户
//            ->find();
//
//        if (!$existingAudit) {
//            return out(null, 10005, '请先完成HVPS开户');
//        }


//        $existingAudit = Db::name('user_open_signatory')
//            ->where('user_id', $user['id'])
//            ->where('status', 1)
//            ->find();
//
//        if ($existingAudit) {
//            return out(null, 10005, '您已经完成签约，不可重复签约');
//        }

        Db::startTrans();
        try {
            $remaining_fee = $fee;

            // 先扣除现金余额
            if ($cash_balance >= $remaining_fee) {
                User::changeInc($user['id'], -$remaining_fee, $cash_wallet, 60, 0, 1, "提额费（现金余额）");
                $remaining_fee = 0;
            } else {
                if($cash_balance>0){
                    User::changeInc($user['id'], -$cash_balance, $cash_wallet, 60, 0, 1, "提额费（现金余额）");
                    $remaining_fee = round($remaining_fee - $cash_balance, 2);
                }
            }

            // 如果现金余额不足，则从团队余额扣除剩余部分
            if ($remaining_fee > 0 && $team_balance > 0 ) {
                if ($team_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $team_wallet, 60, 0, 1, "提额费（团队奖励）");
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$team_balance, $team_wallet, 60, 0, 1, "提额费（团队奖励）");
                    $remaining_fee = round($remaining_fee - $team_balance, 2);
                }
            }

            // 最后扣红包钱包
            if ($remaining_fee > 0 && $red_packet_balance > 0) {
                if ($red_packet_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $red_packet_wallet, 60, 0, 1, "提额费（红包余额）");
                    $remaining_fee = 0;
                }else{
                    User::changeInc($user['id'], -$red_packet_balance, $red_packet_wallet, 60, 0, 1, "提额费（红包余额）", 0, 1);
                    $remaining_fee = round($remaining_fee - $red_packet_balance, 2);
                }
            }

            // 如果红包钱包不足，则从月薪钱包扣除
            if ($remaining_fee > 0 && $salary_balance > 0) {
                if ($salary_balance >= $remaining_fee) {
                    User::changeInc($user['id'], -$remaining_fee, $salary_wallet, 55, 0, 1, "提额费（月薪钱包）", 0, 1);
                    $remaining_fee = 0;
                } else {
                    User::changeInc($user['id'], -$salary_balance, $salary_wallet, 55, 0, 1, "提额费（月薪钱包）", 0, 1);
                    $remaining_fee = round($remaining_fee - $salary_balance, 2);
                }
            }

            // 最终检查：支付是否完全完成
            if ($remaining_fee > 0) {
                return out(null, 10001, '余额不足，支付未完成');
            }

            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];

            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100* $fee, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,0,4,'团队奖励'.$v['level'].'级'.$user['realname']."(银行卡授信签约)",0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::changeInc($user['id'],$fee,'balance',28,0,4,"银行卡授信签约费退还",0,2,'TD');


            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }


            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}