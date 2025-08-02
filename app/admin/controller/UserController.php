<?php

namespace app\admin\controller;

use app\common\Request;
use app\model\AdminOperationLog;
use app\model\AuditRecords;
use app\model\Order5;
use app\model\RelationshipRewardLog;
use app\model\UserCardRecord;
use app\model\UserOpenSignatory;
use app\model\UserPolicySubsidy;
use think\facade\Cache;
use app\model\Apply;
use app\model\Capital;
use app\model\EquityYuanRecord;
use app\model\FamilyChild;
use app\model\Order;
use app\model\PayAccount;
use app\model\Project;
use app\model\User;
use app\model\Promote;
use app\model\Message;
use app\model\Authentication;
use app\model\UserBalanceLog;
use app\model\UserBank;
use app\model\UserProduct;
use app\model\UserRelation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\db\Fetch;
use think\facade\Db;
use think\facade\Session;

class UserController extends AuthController
{
    public function promote()
    {
        $req = request()->param();

        $builder = Promote::order('id', 'desc');
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', 'like', '%' . $req['type'] . '%');
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        if (isset($req['id_card']) && $req['id_card'] !== '') {
            $builder->where('id_card', 'like', '%' . $req['id_card'] . '%');
        }
        if (!empty($req['start_date'])) {
            $builder->where('date_time', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('date_time', '<=', $req['end_date'] . ' 23:59:59');
        }

        if (!empty($req['export'])) {
            $list = $builder->select();
            create_excel($list, [
                'id' => '序号',
                'name'=>'姓名',
                'phone' => '手机号',
                'id_card' => '身份证',
                'type' => '通道',
                'date_time' => '时间',
            ], '' . date('YmdHis'));
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function userList()
    {
        $req = request()->param();

        $builder = User::alias('u')->order('u.id', 'desc');
        // ✅ 开户筛选：audit_type = 5 有记录
        if (isset($req['is_hvps']) && $req['is_hvps'] !== '') {
            $hvpsStart = !empty($req['hvps_start_date']) ? $req['hvps_start_date']. ' 00:00:00': null;
            $hvpsEnd   = !empty($req['hvps_end_date'])   ? $req['hvps_end_date'].   ' 23:59:59': null;

            if ($req['is_hvps'] == 1) {
                // 存在开户记录，支持时间筛选
                $builder->whereExists(function ($query) use ($hvpsStart, $hvpsEnd) {
                    $query->name('audit_records')->alias('a')
                        ->where('a.user_id = u.id')
                        ->where('a.audit_type', 5);

                    if ($hvpsStart) {
                        $query->where('a.created_at', '>=',$hvpsStart);
                    }
                    if ($hvpsEnd) {
                        $query->where('a.created_at', '<=',$hvpsEnd);
                    }
                });
            } elseif ($req['is_hvps'] == 0) {
                // 不存在开户记录，同样支持时间筛选
                $builder->whereNotExists(function ($query) use ($hvpsStart, $hvpsEnd) {
                    $query->name('audit_records')->alias('a')
                        ->where('a.user_id = u.id')
                        ->where('a.audit_type', 5);

                    if ($hvpsStart) {
                        $query->where('a.created_at', '>=',$hvpsStart);
                    }
                    if ($hvpsEnd) {
                        $query->where('a.created_at', '<=',$hvpsEnd);
                    }
                });
            } elseif ($req['is_hvps'] == 2) {
                // 免费开户
                $builder->whereExists(function ($query) use ($hvpsStart, $hvpsEnd) {
                    $query->name('audit_records')->alias('a')
                        ->where('a.user_id = u.id')
                        ->where('a.rejection_reason','免费开户')
                        ->where('a.audit_type', 5);

                    if ($hvpsStart) {
                        $query->where('a.created_at', '>=',$hvpsStart);
                    }
                    if ($hvpsEnd) {
                        $query->where('a.created_at', '<=',$hvpsEnd);
                    }
                });
            }
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('u.id', $req['user_id']);
        }
        if (isset($req['up_user']) && $req['up_user'] !== '') {
            $user_ids = User::where('phone', $req['up_user'])->column('id');
            $user_ids[] = $req['up_user'];
            $builder->whereIn('u.up_user_id', $user_ids);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }
        if (isset($req['invite_code']) && $req['invite_code'] !== '') {
            $builder->where('u.invite_code', $req['invite_code']);
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('u.realname', $req['realname']);
        }
        if (isset($req['level']) && $req['level'] !== '') {
            $builder->where('u.level', $req['level']);
        }
        if (isset($req['is_active']) && $req['is_active'] !== '') {
            if ($req['is_active'] == 0) {
                $builder->where('u.is_active', 0);
            }
            else {
                $builder->where('u.is_active', 1);
            }
        }

        if (!empty($req['start_date'])) {
            $builder->where('u.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('u.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        if (!empty($req['active_date'])) {
            $start = strtotime($req['active_date'] . ' 00:00:00');
            $end = strtotime($req['active_date'] . ' 23:59:59');
            $builder->where('u.active_time', '>=', $start);
            $builder->where('u.active_time', '<=', $end);
        }

        if (isset($req['ic_number']) && $req['ic_number'] !== '') {
            $builder->where('u.ic_number', 'like', '%' . $req['ic_number'] . '%');
        }

        $count = $builder->count();
        $data = $builder->paginate(['query' => $req]);
        //查看有没有政策补贴
        $userIds = array_column($data->items(), 'id');
        $subsidyUserIds = UserPolicySubsidy::whereIn('user_id', $userIds)->column('user_id');
        foreach ($data as &$user) {
            $user['has_subsidy'] = in_array($user['id'], $subsidyUserIds);
        }

        $this->assign('req', $req);
        $this->assign('count', $count);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showUser()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = User::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function message()
    {
        $req = request()->param();
        $this->assign('req', $req);
        return $this->fetch();
    }

    public function addMessage()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'text' => 'require',
        ]);

        Message::insert([
            'from' => 0,
            'to' => $req['id'],
            'text' => $req['text'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return out();
    }

    public function editUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'password|登录密码' => 'max:50',
            'pay_password|支付密码' => 'max:50',
            'realname|实名认证姓名' => 'max:50',
            'ic_number|身份证号' => 'max:50',
        ]);

        if (empty($req['password'])) {
            unset($req['password']);
        }
        else {
            $req['password'] = sha1(md5($req['password']));
        }

        if (empty($req['pay_password'])) {
            unset($req['pay_password']);
        }
        else {
            $req['pay_password'] = sha1(md5($req['pay_password']));
        }
        if(empty($req['realname'])) {
            unset($req['realname']);
        }
        if(empty($req['ic_number'])) {
            unset($req['ic_number']);
        }
        // if (empty($req['realname']) && !empty($req['ic_number'])) {
        //     return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        // }
        // if (!empty($req['realname']) && empty($req['ic_number'])) {
        //     return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        // }

        // 判断给直属上级额外奖励
        if (!empty($req['ic_number'])) {
            if (User::where('ic_number', $req['ic_number'])->where('id', '<>', $req['id'])->count()) {
                return out(null, 10001, '该身份证号已经实名过了');
            }

            // $user = User::where('id', $req['id'])->find();
            // if (!empty($user['up_user_id']) && empty($user['ic_number'])) {
            //     User::changeBalance($user['up_user_id'], dbconfig('direct_recommend_reward_amount'), 7, $user['id']);
            // }
        }

        User::where('id', $req['id'])->update($req);

        // 把注册赠送的股权给用户
        EquityYuanRecord::where('user_id', $req['id'])->where('type', 1)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        return out();
    }

    public function changeUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        User::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function editPhone(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'phone|手机号' => 'require|mobile',
            ]);
            $new = User::where('phone',$req['phone'])->find();
            if($new){
                return out(null,10001,'已有的手机号');
            }
            $user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['phone'=>$req['phone'],'prev_phone'=>$user['phone']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function editRelease(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'private_release|释放提现额度' => 'require',
            ]);
            //$user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['private_release'=>$req['private_release']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function editBank(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'bank_name|姓名' => 'require',
                'bank_number|银行卡号' => 'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['bank_name'=>$req['bank_name'],'bank_number'=>$req['bank_number']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function showChangeBalance()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
            'type' => 'require|in:15,16',
        ]);

        $this->assign('req', $req);

        return $this->fetch();
    }

    public function showChangeOrder()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
            'type' => 'require|in:15,16',
        ]);

        $this->assign('req', $req);
        $this->assign('project1', Project::where('project_group_id', '>=', 5)->order('id', 'desc')->select()->toArray());

        return $this->fetch();
    }

    public function batchShowBalance()
    {
        $req = request()->get();

        return $this->fetch();
    }

    public function addBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        $adminUser = $this->adminUser;
//        $filed = 'topup_balance';
//        $log_type = 0;
//        $balance_type = 1;
//        $text = '现金';
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                $text = '余额';
                break;
            case 2:
                $filed = 'yixiaoduizijin';
                $log_type = 7;
                $balance_type = 15;
                $text = '收益';
                break;
            case 3:
                $filed = 'yixiaoduizijin';
                $log_type = 7;
                $balance_type = 15;
                $text = '生育津贴';
                break;
            case 4:
                $filed = 'xuanchuan_balance';
                $log_type = 4;
                $balance_type = 8;
                $text = '团队奖励';
                break;
            case 5:
                $filed = 'team_bonus_balance';
                $log_type = 7;
                $balance_type = 15;
                $text = '月薪钱包';
                break;
            case 6:
                $filed = 'shenianhongbao';
                $log_type = 6;
                $balance_type = 15;
                $text = '红包余额';
                break;
            case 7:
                $filed = 'yixiaoduizijin';
                $log_type = 7;
                $balance_type = 15;
                $text = '已校对资金转入';
                break;
            case 8:
                $filed = 'yu_e_bao';
                $log_type = 8;
                $balance_type = 15;
                $text = '余额宝';
                break;
            case 9:
                $filed = 'buzhujin';
                $log_type = 9;
                $balance_type = 15;
                $text = '补助金';
                break;
            case 10:
                $filed = 'shouyijin';
                $log_type = 10;
                $balance_type = 15;
                $text = '收益金';
                break;
            case 12:
                $filed = 'yu_e_bao_shouyi';
                $log_type = 12;
                $balance_type = 15;
                $text = '余额宝收益';
                break;
            case 13:
                $filed = 'zhaiwujj';
                $log_type = 13;
                $balance_type = 15;
                $text = '余额宝收益';
                break;
            default:
                return out(null, 10001, '类型错误');
        }

        if (0>$req['money']){
            $str = '金额转出';
        } else {
            $str = '金额转入';
        }
        if ($req['type'] == 4){
            $str = '团队奖励';
        }
        if ($req['type'] == 5){
            $str = '月薪钱包';
        }
        if ($req['type'] == 6){
            $str = '红包余额';
        }
        if ($req['type'] == 8){
            $str = '余额宝收益金金额转入';
        }
        if ($req['type'] == 9){
            $str = '补助金金额转入';
        }
        if ($req['type'] == 10){
            $str = '收益金金额转入';
        }
        if ($req['type'] == 2){
            $str = '收益';
        }
        if ($req['type'] == 3){
            $str = '生育津贴';
        }
        if ($req['type'] == 5){
            $str = '生育补贴';
        }
        if ($req['type'] == 13){
            $str = '债务基金转入';
        }
        if ($req['type'] == 15){
            $str = '服务台转入';
        }

        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
//        $text = !isset($req['remark']) || $req['remark']==''?$str.$text:$req['remark'];
        $text = !isset($req['remark']) || $req['remark'] == '' ? $str : $req['remark'];
        User::changeInc($req['user_id'],$req['money'],$filed,$balance_type,0,$log_type,$text,$adminUser['id']);

        //写入日志
        $adminUserName = Session::get('admin_user');
        AdminOperationLog::add($req['user_id'],$adminUserName->account,'update','为用户id='.$req['user_id'].'-'.$text.'-入金 ' .$req['money']);

        if($filed == 'topup_balance') {
            User::where('id', $req['user_id'])->inc('total_recharge', intval($req['money']))->update();
//            $user = User::where('id', $req['user_id'])->find();
            // if ($user['is_active'] == 0) {
            //     User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
            //     // 下级用户激活
            //     UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            // }
        }

        // 保存充值记录
        if($req['type'] == 6) {
            $userInfo = User::where('id',$req['user_id'])->find();
            $capital_sn = build_order_sn($req['user_id']);
            Capital::create([
                'user_id' => $req['user_id'],
                'capital_sn' => $capital_sn,
                'type' => 1,
                'pay_channel' => 1,
                'amount' => $req['money'],
                'withdraw_amount' => $req['money'],
                'withdraw_fee' => 0,
                'realname' => $userInfo['realname'],
                'phone' => $userInfo['phone'],
                'account' => $req['money'],
                'log_type' => $log_type,
                'audit_remark' => $str,
                'status' => 2
            ]);
        }


        return out();
    }

    public function addOrder()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'project_id'=>'require|number',
        ]);

        $clickRepeatName = 'addOrder-' . $req['user_id'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $user = User::find($req['user_id']);
        $order_sn = 'JH'.build_order_sn($user['id']);
        $project = Project::find($req['project_id']);
        $pay_amount = $project['price'];
        Order::create([
            'user_id' => $req['user_id'],
            'up_user_id' => $user['up_user_id'],
            'order_sn' => $order_sn,
            'status' => 1,
            'buy_num' => 1,
            'pay_method' => $req['pay_method'] ?? 1,
            'price' => $pay_amount,
            'buy_amount' => $pay_amount,
            'shouyi' => $project['shouyi'],
            'project_id' => $project['id'],
            'project_name' => $project['name'],
            'shengyu' => $project['shengyu'],
            'cover_img' => $project['cover_img'],
            'shengyu_butie' => $project['shengyu_butie'],
            'zhaiwujj' => $project['zhaiwujj'],
            'buzhujin' => $project['buzhujin'],
            'shouyijin' => $project['shouyijin'],
            'project_group_id' => $project['project_group_id'],
            
            'is_gift' => 1,
        ]);


        if ($user['is_active'] == 0) {
            User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
            UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
        }

        if ($project['name'] == "科技与社会发展"){
            UserCardRecord::create([
                'user_id' => $req['user_id'],
                'fee' => $pay_amount,
                'status' => 2,//初始话办卡中
                'card_id' => 5,
                'bank_card' => '62'.time().$user['id'],
            ]);
        }

        return out();
    }

    public function batchBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'users' => 'require',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        $phoneList = explode(PHP_EOL, $req['users']);
        if(count($phoneList)<=0){
            return out(null, 10001, '用户不能为空');
        }
        $adminUser = $this->adminUser;
        $filed = 'balance';
        $log_type = 0;
        $balance_type = 1;
        $text = '余额';
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                $text = '余额';
                break;
            case 2:
                $filed = 'income_balance';
                $log_type = 2;
                $balance_type = 15;
                $text = '收益';
                break;
            case 3:
                $filed = 'shengyu_balance';
                $log_type = 3;
                $balance_type = 15;
                $text = '生育津贴';
                break;
            case 4:
                $filed = 'xuanchuan_balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '宣传奖励';
                break;
            case 5:
                $filed = 'shengyu_butie_balance';
                $log_type = 5;
                $balance_type = 15;
                $text = '生育补贴';
                break;
            default:
                return out(null, 10001, '类型错误');
        }
        $str = '客服专员入金';
        if (0>$req['money']){
            $str = '金额转出';
        } else {
            $str = '金额转入';
        }
        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
        $text = !isset($req['remark']) || $req['remark'] == '' ? $str : $req['remark'];
        foreach($phoneList as $key=>$phone){
            $phoneList[$key] = trim($phone);
        }
        $ids = User::whereIn('phone',$phoneList)->column('id');
        Db::startTrans();
        try{
            foreach($ids as $id){
                User::changeInc($id,$req['money'],$filed,$balance_type,0,$log_type,$text,$adminUser['id']);
            }
        }catch(\Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage());
        }
        Db::commit();

        return out();
    }

    public function deductBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'remark' => 'max:50',
        ]);
        $adminUser = $this->adminUser;

        $user = User::where('id', $req['user_id'])->find();
        if ($user['balance'] < $req['money']) {
            return out(null, 10001, '用户余额不足');
        }

        if (Capital::where('user_id', $user['id'])->where('type', 2)->where('pay_channel', 1)->where('status', 1)->count()) {
            return out(null, 10001, '该用户有待审核的手动出金，请先去完成审核');
        }

        // 保存到资金记录表
        Capital::create([
            'user_id' => $user['id'],
            'capital_sn' => build_order_sn($user['id']),
            'type' => 2,
            'pay_channel' => 1,
            'amount' => 0 - $req['money'],
            'withdraw_amount' => $req['money'],
            'audit_remark' => $req['remark'] ?? '',
            'admin_user_id' => $adminUser['id'],
        ]);

        return out();
    }

    public function userTeamList()
    {
        $req = request()->get();

        $user = User::where('id', $req['user_id'])->find();

        $data = ['user_id' => $user['id'], 'phone' => $user['phone']];

        $total_num = UserRelation::where('user_id', $req['user_id']);
        $active_num = UserRelation::where('user_id', $req['user_id'])->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num'] = $total_num->count();
        $data['active_num'] = $active_num->count();


        $total_num1 = UserRelation::where('user_id', $req['user_id'])->where('level', 1);
        $active_num1 = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num1->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num1->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num1'] = $total_num1->count();
        $data['active_num1'] = $active_num1->count();

        $total_num2 = UserRelation::where('user_id', $req['user_id'])->where('level', 2);
        $active_num2 = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num2->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num2->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num2'] = $total_num2->count();
        $data['active_num2'] = $active_num2->count();

        $total_num3 = UserRelation::where('user_id', $req['user_id'])->where('level', 3);
        $active_num3 = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num3->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num3->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num3'] = $total_num3->count();
        $data['active_num3'] = $active_num3->count();

        $this->assign('data', $data);
        $this->assign('req', $req);
        return $this->fetch();
    }
    public function KKK(){
        $a = User::field('id,up_user_id,is_active')->limit(0,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(150000,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(300000,150000)->select()->toArray();
        // echo '<pre>';print_r($a);die;
        $re = $this->tree($a,4);
        echo count($re);
    }

    public function tree($data,$pid){
        static $arr = [];
        foreach($data as $k=>$v){
            if($v['up_user_id']==$pid && $v['is_active'] == 1){
                $arr[] = $v;
                unset($data[$k]);
                $this->tree($data,$v['id']);
            }
        }
        return $arr;
    }

    /**
     * 实名认证
     */
    public function authentication()
    {
        $req = request()->param();

        $builder = Authentication::order('id', 'desc');
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('id', $req['user_id']);
        }
        //   if (isset($req['up_user']) && $req['up_user'] !== '') {
        //       $user_ids = User::where('phone', $req['up_user'])->column('id');
        //       $user_ids[] = $req['up_user'];
        //       $builder->whereIn('up_user_id', $user_ids);
        //   }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', $req['phone']);
        }
        //   if (isset($req['invite_code']) && $req['invite_code'] !== '') {
        //       $builder->where('invite_code', $req['invite_code']);
        //   }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('realname', $req['realname']);
        }
        //   if (isset($req['level']) && $req['level'] !== '') {
        //       $builder->where('level', $req['level']);
        //   }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    /**
     * 实名认证通过
     */
    public function pass()
    {
        $req = request()->param();
        $authentication = Authentication::find($req['id']);
        User::where('id', $authentication['user_id'])->data(['realname' => $authentication['realname'],'ic_number'=>$authentication['id_card_number']])->update();
        Authentication::where('id', $req['id'])->data(['status' => 1])->update();
        return out();
    }

    /**
     * 实名认证拒绝
     */
    public function reject()
    {
        $req = request()->param();
        Authentication::where('user_id', $req['id'])->data(['status' => 2])->update();
        return out();
    }

    public function pass_reject()
    {
        $req = request()->param();
        $Authentication = Authentication::where('id', $req['id'])->find();
        $Authentication->delete();
        User::where('id',  $Authentication['user_id'])->data(['realname' => '','ic_number'=>''])->update();

        return out();
    }

    // 自动审核流程 里面 本人和家庭成员
    public function audit_reject()
    {
        $req = request()->param();
        $Audit = AuditRecords::where('id', $req['id'])->update(['status'=>3]);
        return out();
    }

    /**
     * 导入
     */
    public function import()
    {
        return $this->fetch();
    }

    public function importSubmit()
    {
        $file = upload_file3('file');
        $spreadsheet = IOFactory::load($file);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();
        $newArr = [];
        foreach ($sheetData as $key => $value) {
            $res = Db::name('user')->field('realname, phone, id')->where('realname', $value[0])->select()->toArray();
            if (count($res) == 1) {
                $singleArr = $res[0];
                $singleArr['amount'] = $value[1];
                $singleArr['remark'] = $value[2];
                $singleArr['head'] = 0;
                array_push($newArr, $singleArr);
            } else {
                foreach ($res as $v) {
                    $mulArr = $v;
                    $mulArr['amount'] = $value[1];
                    $mulArr['remark'] = $value[2];
                    $mulArr['head'] = 1;
                    array_unshift($newArr, $mulArr);
                }
            }
        }
        return out($newArr);
    }

    public function importExec()
    {
        $req = request()->param();
        $ids = $req['ids'];
        Db::startTrans();
        try {
            foreach ($ids as $key => $value) {
                User::changeInc($value['id'],$value['amount'],'balance',99,$value['id'],2, $value['remark'],'',1,'IM');
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            //return $e->getMessage();
            throw $e;
        }
        return out();
    }

    //银行卡列表
    public function bankList(Request $request)
    {
        $req = $request->param();
        $builder = UserBank::where('user_id', $req['user_id']);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //银行卡修改状态
    public function bankedit(Request $request)
    {
        $req = $request->param();
        $res = UserBank::where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    //银行卡删除
    public function bankdel(Request $request)
    {
        $req = $request->param();
        $res = UserBank::where('id', $req['id'])->delete();
        return out();
    }

    //开户认证
    public function openAuth(Request $request)
    {
        $req = $request->param();
        $builder = Apply::where('user_id', $req['user_id']);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        return view();
    }

    //户口认证列表
    public function homelandList(Request $request)
    {
        $req = $request->param();
        $req['type'] = 6;
        $builder = Db::table('mp_family_child')->alias('a')
                        ->field('a.id, a.user_id,a.my, a.created_at, a.child1, a.child2, a.child3, a.family_members, a.family_address, b.realname')
                        ->leftJoin('mp_user b','a.user_id = b.id')
                        ->where('a.user_id', $req['user_id']);

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        return view();
    }


    //看房
    public function reserveHouse(Request $request)
    {
        $req = $request->param();
        $builder = Db::table('mp_reserve')->where('user_id', $req['user_id'])->where('type',1);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //看房
    public function reserveCar(Request $request)
    {
        $req = $request->param();
        $builder = Db::table('mp_reserve')->where('user_id', $req['user_id'])->where('type',2);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //修改状态
    public function reserveEditStatus(Request $request)
    {
        $req = $request->param();
        $res = Db::table('mp_reserve')->where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    //获取我的地址
    public function address(Request $request)
    {
        $req = $request->param();
        $data = Db::table('mp_user_delivery')->where('user_id', $req['user_id'])->select();

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('count', $data->count());
        return view();
    }

    //银行卡页面展示
    public function showBank(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        return $this->fetch();
    }

    //添加银行卡
    public function addBank(Request $request)
    {
        $req = $this->validate(request(), [
            'user_id|用户id' => 'require',
            'name|姓名' => 'require',
            'bank_name|银行名称' => 'require',
            'bank_address|银行地址' => 'require',
            'bank_sn|银行卡号' => 'require',
        ]);

        $data = [
            'user_id'   => $req['user_id'],
            'name'      => $req['name'],
            'bank_name' => $req['bank_name'],
            'bank_address' => $req['bank_address'],
            'bank_sn'   => $req['bank_sn'],
            'reg_date'  => date('Y-m-d H:i:s'),
            'status'    => 0,
        ];
        UserBank::create($data);
        return out(null,200,'成功');
    }

    //展示身份证
    public function showUserNum(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        return $this->fetch();
    }

    //添加身份证
    public function addUserNum(Request $request)
    {
        $req = $this->validate(request(), [
            'user_id|用户id' => 'require',
        ]);

        $req['id_card_front'] = upload_file('cover_img1');
        $req['id_card_opposite'] = upload_file('cover_img2');

        $data = [
            'user_id'       => $req['user_id'],
            'type'          => 5,
            'id_card_front' => $req['id_card_front'],
            'id_card_opposite' => $req['id_card_opposite'],
            'created_at'    => date('Y-m-d H:i:s'),
            'status'        => 1,
        ];
        Apply::create($data);
        return out(null,200,'成功');
    }

    public function delUserNum()
    {
        $req = $this->validate(request(), [
            'id|用户id' => 'require',
        ]);

        Apply::where('id',$req['id'])->delete();
        return out([null,200,'成功']);
    }

    //家庭，展示
    public function showFamily(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        return $this->fetch();
    }

    //添加家庭成员
    public function addUserFamily()
    {
        $req = request()->param();

        if ($my = upload_file('cover_img1', false)) {
            $req['my'] = $my;
        }

        $req['child1'] = upload_file('cover_img2');

        if ($cover_img3 = upload_file('cover_img3', false)) {
            $req['child2'] = $cover_img3;
        }

        if ($cover_img4 = upload_file('cover_img4', false)) {
            $req['child3'] = $cover_img4;
        }

        $data = [
            'user_id'=> $req['user_id'],
            'my'     => $req['my'] ?? "",
            'child1' => $req['child1'] ?? "",
            'child2' => $req['child2'] ?? "",
            'child3' => $req['child3'] ?? "",
            'family_members' => $req['family_members'],
            'family_address' => $req['family_address'],
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        FamilyChild::create($data);
        return out(null,200,'成功');
    }

    //删除家庭成员
    public function delUserFimly()
    {
        $req = $this->validate(request(), [
            'id|id' => 'require',
        ]);

        FamilyChild::where('id',$req['id'])->delete();
        return out([null,200,'成功']);
    }

    //产品-用户人数
    public function userNums(Request $request)
    {
        $req = request()->param();

        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();

        // 一级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 1)->column('sub_user_id');
        //分组id
        $a = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $a->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $a->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $a->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $a->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level1 = $a->count();

        // 二级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 2)->column('sub_user_id');
        $b = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $b->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $b->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $b->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $b->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level2 = $b->count();

        // 三级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 3)->column('sub_user_id');
        $c = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $c->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $c->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] !== '') {
            $c->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] !== '') {
            $c->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level3 = $c->count();

        if ((isset($req['project_group_id']) && $req['project_group_id'] !== '')  && (isset($req['project_id']) && $req['project_id'] !== '')){
            $data['level1_total'] = $level1;
            $data['level2_total'] = $level2;
            $data['level3_total'] = $level3;
        }

        $data['count'] = $data['level1_total'] + $data['level2_total'] + $data['level3_total'];

        $groupList = [
            "5" => "最后阶段"
        ];

        $projectList = [
            "117" => "全免费入学发展",
            "118" => "全免费医药发展",
            "119" => "科教与人才发展",
            "120" => "农村与农业发展",
            "121" => "科技与社会发展"
        ];
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('projectList', $projectList);
        $this->assign('groupList', $groupList);
        return view();
    }

    public function projectList(Request $request)
    {
        $req = request()->param();

        $projectList = [];
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $projectList = Project::where('project_group_id',$req['project_group_id'])->select();
        }
        return out($projectList);
    }

    public function applyedit(Request $request)
    {
        $req = $request->param();
        Apply::where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    public function showChangeJiaoNa()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
        ]);

        $user = User::where('id',$req['user_id'])->find();
        //总数
        $data['total'] = $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
        //需缴纳
        $data['price'] = round($data['total'] * 0.02,2);
        //余额
        $data['topup_balance'] = $user['topup_balance'];
        //垫付
        $data['gift_prize'] = 0;
        if ($data['price'] > $data['topup_balance']) {
            $data['gift_prize'] = abs($data['topup_balance'] - $data['price']);
        }

        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function addJiaoNa()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'gift_prize' => 'require|number',
        ]);

        $clickRepeatName = 'product5-pay-' . $req['user_id'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }

        Cache::set($clickRepeatName, 1, 5);

        $order5count = Order5::where('user_id',$req['user_id'])->count();
        if ($order5count >= 1){
            return out(null, 10001, '已赠送');
        }

        Db::startTrans();
        try {
            $user = User::where('id',$req['user_id'])->find();
            //资金来源 已校对资金 债务基金 余额宝 补助金 收益金
            $total = $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
            $jiaofei = round($total * 0.02,2);
            $time = date('Y-m-d H:i:s');

            //钱不够
            if ($jiaofei > $user['topup_balance']) {
                User::changeInc1($user['id'],$req['gift_prize'],'topup_balance',103,$user['id'],1,'预付金',0,1);

                $order = Order5::create([
                    'user_id' => $req['user_id'],
                    'price' => $jiaofei,
                    'total' => $total,
                    'created_at' => $time,
                    'updated_at' => $time,
                    'is_gift' => 1,
                    'gift_prize' => $req['gift_prize'],
                ]);
                User::changeInc1($user['id'],-$jiaofei,'topup_balance',3,$order['id'],1,'资金来源证明',0,1);

                // 给上3级团队奖（迁移至申领）
                $relation = UserRelation::where('sub_user_id', $user['id'])->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$user['topup_balance'], 2);
                    if($reward > 0){
                        User::changeInc1($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                        RelationshipRewardLog::insert([
                            'uid' => $v['user_id'],
                            'reward' => $reward,
                            'son' => $user['id'],
                            'son_lay' => $v['level'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }


                if ($user['is_active'] == 0) {
                    User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                    UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                }
            } else {
                $price = $jiaofei;
                //钱够
                $order = Order5::create([
                    'user_id' => $req['user_id'],
                    'price' => $price,
                    'total' => $total,
                    'created_at' => $time,
                    'updated_at' => $time,
                    'is_gift' => 1,
                    'gift_prize' => $req['gift_prize'],
                ]);
                User::changeInc($user['id'],-$price,'topup_balance',3,$order['id'],1,'资金来源证明',0,1);

                // 给上3级团队奖（迁移至申领）
                $relation = UserRelation::where('sub_user_id', $user['id'])->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$price, 2);
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

                //增加预付金
                if ($req['gift_prize'] > 0){
                    //增加 预付金
                    User::changeInc($user['id'],$req['gift_prize'],'topup_balance',103,$user['id'],1,'预付金',0,1);
                }

                if ($user['is_active'] == 0) {
                    User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                    UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //缴纳人数
    public function jiaoNaNums()
    {
        $req = request()->param();
        $data['today'] = date('Y-m-d');

        // 一级团队人数
        //$data['level1_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('created_at', '>=', $data['today'].' 00:00:00')->count();
        // 二级团队人数
        //$data['level2_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('created_at', '>=', $data['today'].' 00:00:00')->count();
        // 三级团队人数
        //$data['level3_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('created_at', '>=', $data['today'].' 00:00:00')->count();

        // 一级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 1)->column('sub_user_id');
        $a = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $a->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $a->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $a->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level1 = $a->count();

        // 二级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 2)->column('sub_user_id');
        $b = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $b->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $b->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $b->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level2 = $b->count();

        // 三级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 3)->column('sub_user_id');
        $c = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $c->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $c->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $c->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level3 = $c->count();

//        $data['totalLevel'] = $data['level1_total'] + $data['level2_total'] + $data['level3_total'];
        $data['count'] = $level1 + $level2 + $level3;
        $data['level1'] = $level1;
        $data['level2'] = $level2;
        $data['level3'] = $level3;

        $this->assign('req', $req);
        $this->assign('data', $data);
        return view();
    }


    public function openSignatoryList()
    {
        $req = request()->param();
        $builder = UserOpenSignatory::alias('p')->order('p.id', 'desc');

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('p.phone', $req['phone']);
        }


        $count = $builder->count();
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $count);
        $this->assign('data', $data);

        return $this->fetch();
    }
}
