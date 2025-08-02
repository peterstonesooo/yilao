<?php

namespace app\api\controller;

use app\model\Apply;
use app\model\Capital;
use app\model\FamilyChild;
use app\model\Order5;
use app\model\PayAccount;
use app\model\Timing;
use app\model\UserBank;
use app\model\UserProduct;
use app\model\UserSignin;
use app\model\YuanmengUser;
use app\model\Message;
use app\model\Coin;
use app\model\Order;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use app\model\KlineChartNew;
use app\model\HongliOrder;
use app\model\Hongli;
use app\model\Authentication;
use app\model\ButieOrder;
use app\model\Certificate;
use app\model\Payment;
use app\model\RelationshipRewardLog;
use app\model\Taxoff;
use app\model\UserCoinBalance;
use app\model\Order4;
use think\facade\Cache;
use app\model\UserDelivery;
use app\model\UserPrivateBank;
use app\model\WalletAddress;
use app\model\ZhufangOrder;
use app\model\UserPolicySubsidy;
use think\facade\Db;
use Exception;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use think\facade\App;

use think\Request;
use function PHPSTORM_META\map;

class UserController extends AuthController
{
    public function userInfo()
    {
        $user = $this->user;

        //$user = User::where('id', $user['id'])->append(['equity', 'digital_yuan', 'my_bonus', 'total_bonus', 'profiting_bonus', 'exchange_equity', 'exchange_digital_yuan', 'passive_total_income', 'passive_receive_income', 'passive_wait_income', 'subsidy_total_income', 'team_user_num', 'team_performance', 'can_withdraw_balance'])->find()->toArray();
        $user = User::where('id', $user['id'])
                    ->field('id,phone,realname,subsidy_amount,pay_password,up_user_id,is_active,
                                invite_code,ic_number,level,balance,topup_balance,poverty_subsidy_amount,
                                digital_yuan_amount,can_open_digital,team_bonus_balance,created_at,qq,avatar,
                                monthly_subsidy,digit_balance,private_bank_open,private_bank_balance,bond_open,
                                bond_balance,all_digit_balance,jijin_shenbao_amount,yuan_shenbao_amount,
                                release_balance,sn_no,topup_balance,income_balance,shengyu_balance,
                                xuanchuan_balance,shengyu_butie_balance,address,bank_number,zhaiwujj,
                                yixiaoduizijin,yu_e_bao,yu_e_bao_shouyi,buzhujin,shouyijin,shenianhongbao')
                    ->find()
                    ->toArray();
    
        $user['is_set_pay_password'] = !empty($user['pay_password']) ? 1 : 0;
        $user['wallet_address'] = '';
        unset($user['password'], $user['pay_password']);
        $delivery=UserDelivery::where('user_id', $user['id'])->find();
        if($delivery){
            $user['my_address']=$delivery;
        }else{
            $user['my_address']=array();
        }
        $wallet_address = WalletAddress::where('user_id', $user['id'])->find();
        if($wallet_address){
            $user['wallet_address']=$wallet_address['address'];
        }
        $capital1 = Capital::where('user_id', $user['id'])->whereIn('type',[3,4,5])->whereIn('status', [1,2])->sum('withdraw_amount');
        $capital2 = Capital::where('user_id', $user['id'])->whereIn('log_type',[2,5])->whereIn('status', [1,2])->sum('withdraw_amount');
        $user['total_assets'] = bcadd(($user['income_balance'] + $user['shengyu_butie_balance']), ($capital1 + $capital2), 2);
     
        $isZhaiwujj = 0;
        $zhaiwuOrder = Order::where('user_id', $user['id'])->where('project_group_id', 5)->find();
        if ($zhaiwuOrder != null) {
            $isZhaiwujj = 1;
        }
        $user['isZhaiwujj'] = $isZhaiwujj;

        $isOrder4 = 0;
        $zhaiwuOrder = Order4::where('user_id', $user['id'])->find();
        if ($zhaiwuOrder != null) {
            $isOrder4 = 1;
        }
        $user['isOrder4'] = $isOrder4;

        $user['phone'] = substr_replace($user['phone'],'****', 3, 4);

        $user['totalbalance'] = $user['topup_balance']+$user['xuanchuan_balance']+$user['shenianhongbao'];
        $isOrder5 = 0;
        $zijinlaiyuanOrder = Order5::where('user_id', $user['id'])->count();
        if ($zijinlaiyuanOrder == 1) {
            $isOrder5 = 1;
        }
        $user['isOrder5'] = $isOrder5;
        $user['xianjin'] = $user['topup_balance']+$user['xuanchuan_balance'];//提现页面钱包余额
        //资金来源 已校对资金 债务基金 余额宝 补助金 收益金
        $user['zijinlaiyuan'] = $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
        $user['zijinlaiyuan_jiaofei'] = round($user['zijinlaiyuan'] * 0.02,2);

        return out($user);
    }

    public function releaseConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $shenbao = $user['jijin_shenbao_amount'] + $user['yuan_shenbao_amount'];

        if($shenbao <= 0) {
            return out(null, 10001, '请先进行申购纳税');
        }

        if($shenbao < 1000000) {
           
            return out(['release' => 3000]);
            
        } elseif ($shenbao >= 1000000 && $shenbao < 5000000) {
            return out(['release' => 4000]);
        } elseif ($shenbao >= 5000000) {
            return out(['release' => 5000]);
        }
        
    }

    public function userSnno()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $shenbao = $user['jijin_shenbao_amount'] + $user['yuan_shenbao_amount'];
        if($shenbao <= 0) {
            return out(null, 10001, '请先进行申购纳税');
        }

        if($user['sn_no']) {
            return out(['sn_no' => $user['sn_no']]);
        } else {
            $rand = 'SN'.str_pad(mt_rand(0, 99999999), 8, '1', STR_PAD_LEFT).'BTK';
            try {
                User::where('id', $user['id'])->update(['sn_no' => $rand]);
            } catch (Exception $e) {
                return out(null, 10001, '网络问题，请重试');
            }
            return out(['sn_no' => $rand]);
        }
    }

    public function userBank()
    {
        $user = $this->user;
        $data = UserPrivateBank::where('user_id', $user['id'])->select();
        return out($data);
    }

    public function userFund()
    {
        $user = $this->user;

        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_digital_yuan_amount,used_monthly_subsidy,used_checkingAmount,used_digital_gift')->find()->toArray();
        //审核中金额
        $user['checkingAmount'] = YuanmengUser::where('user_id', $user['id'])->where('order_status', 1)->sum('amount');
        
        $gift = Order::where('user_id', $user['id'])->where('project_group_id', 3)->find();
        if($gift) {
            $user['digital_gift'] = $gift['sum_amount'];
        } else {
            $user['digital_gift'] = 0;
        }
        $user['digital_gift'] = bcsub($user['digital_gift'], $user['used_digital_gift'], 2);
        $user['checkingAmount'] = bcsub($user['checkingAmount'], $user['used_checkingAmount'], 2);
        if($user['checkingAmount'] < 0) {
            $user['checkingAmount'] = 0;
        }
        $user['digital_yuan_amount'] = bcsub($user['digital_yuan_amount'], $user['used_digital_yuan_amount'], 2);
        $user['monthly_subsidy'] = bcsub($user['monthly_subsidy'], $user['used_monthly_subsidy'], 2);
        
        $user['all'] = $user['digital_gift'] + $user['checkingAmount']+ $user['digital_yuan_amount']+ $user['monthly_subsidy'];

        $user['all'] = round($user['all'], 0);

        $user['total_fund'] = Order::where('user_id', $user['id'])->where('project_group_id', 2)->sum('all_bonus');
        return out($user);
    }



    public function getName()
    {
        $req = $this->validate(request(), [
            'phone|手机号' => 'require',
        ]);
        $user = User::where('phone', $req['phone'])->field('realname')->find();

        if(!$user) {
            return out(null, 10001, '用户不存在');
        } else {
            return out($user);
        }
    }

    public function applyMedal(){
        $req = $this->validate(request(), [
            'address|详细地址' => 'require',
        ]);
        $user = $this->user;


        UserDelivery::updateAddress($user,$req);
        $subCount = UserRelation::where('user_id',$user['id'])->where('is_active',1)->count();
        if($subCount<500){
            return out(null,10002,'激活人数不足500人');
        }
        $msg = Apply::add($user['id'],1);
        if($msg==""){
            return out();
        }else{
            return out(null,10003,$msg);
        }

    }
    public function applyHouse(){
        $user = $this->user;
        $is_three_stage = User::isThreeStage($user['id']);
        if(!$is_three_stage){
            return out(null,10001,'暂未满足条件');
        }
        $msg = Apply::add($user['id'],2);
        if($msg==""){
            return out();
        }else{
            return out(null,10002,"预约看房申请已提交，请耐心等待，留意好您的手机。");
        }
    }
    public function applyCar(){
        $user = $this->user;
        $count = UserRelation::where('user_id',$user['id'])->where('is_active',1)->count();
        if($count<1000){
            $projectIds = [53,54,55,56,57];
            foreach($projectIds as $v){
                $order = Order::where('user_id',$user['id'])->where('project_group_id',4)->where('project_id',$v)->where('status','>=',2)->find();
                if(!$order){
                    return out(null,10001,'暂未满足条件');
                }
            }
       }
        $msg = Apply::add($user['id'],3);
        if($msg==""){
            return out();
        }else{
            return out(null,10002,"预约提车申请已提交，请耐心等待，留意好您的手机。");
        }
    }

    public function myHouse(){
        $user = $this->user;
        $data = User::myHouse($user['id']);
        if($data['msg']!=''){
            return out(null,10001,$data['msg']);
        }
        $house = $data['house'];
        $coverImg = Project::where('id',$house['project_id'])->value('cover_img');
        $houseFee = \app\model\HouseFee::where('user_id',$user['id'])->find();
        $data = [
            'name'=>$house['project_name'],
            'cover_img'=>$coverImg,
            'is_house_fee'=>$houseFee?1:0,
        ];
        
        return out($data);
    }

    public function cardAuth(){
        $user = $this->user;
        $order = Order::where('user_id',$user['id'])->where('project_group_id',5)->where('status','>=',2)->find();
        if(!$order){
            return out(null,10001,'请先购买办卡项目');
        }
        $req= $this->validate(request(), [
            'realname|真实姓名' => 'require',
            'ic_number|身份证号' => 'require',
        ]);
        if($user['realname']=='' || $user['ic_number']==''){
            return out(null,10002,'请先完成实名认证');
        }
        if($user['realname']!=$req['realname'] || $user['ic_number']!=$req['ic_number']){
            return out(null,10003,'与实名认证信息不一致');
        }
        $msg = Apply::add($user['id'],5);
        if($msg==""){
            return out();
        }else if($msg=="已经申请过了"){
            return out();
        }else{
            return out(null,10004,$msg);
        }
    }

    public function cardProgress(){
        $user = $this->user;
        $apply = Apply::where('user_id',$user['id'])->where('type',5)->find();
        if(!$apply){
            return out(null,10001,'请先开户认证');
        }
        $order = Order::where('user_id',$user['id'])->where('project_group_id',5)->where('status','>=',2)->select();
        $data = [];
        //$ids = [];
        foreach($order as $v){
            // if(isset($ids[$v['project_id']])){
            //     continue;
            // }
            $data[] = [
                'name'=>$v['project_name'],
                'cover_img'=>get_img_api($v['cover_img']),
            ];
            //$ids[$v['project_id']] = 1;
        }
        
        return out($data);
    }

    //邀请
    public function invite(){
        $user = $this->user;
        $host = env('app.host', '');
        $frontHost = env('app.front_host', 'https://h5.zdrxm.com');
       
        $url = "$frontHost/#/pages/system-page/gf_register?invite_code={$user['invite_code']}";
        $img = $user['invite_img'];
//        if($img==''){
//            $qrCode = QrCode::create($url)
//            // 内容编码
//            ->setEncoding(new Encoding('UTF-8'))
//            // 内容区域大小
//            ->setSize(200)
//            // 内容区域外边距
//            ->setMargin(10);
//            // 生成二维码数据对象
//            $result = (new PngWriter)->write($qrCode);
//            // 直接输出在浏览器中
//            // ob_end_clean(); //处理在TP框架中显示乱码问题
//            // header('Content-Type: ' . $result->getMimeType());
//            // echo $result->getString();
//            // 将二维码图片保存到本地服务器
//            $today = date("Y-m-d");
//            $basePath = App::getRootPath()."public/";
//            $path =  "storage/qrcode/$today";
//            if(!is_dir($basePath.$path)){
//                mkdir($basePath.$path, 0777, true);
//            }
//            $name = "{$user['id']}.png";
//            $filePath = $basePath.$path.'/'.$name;
//            $result->saveToFile($filePath);
//            $img = $path.'/'.$name;
//            User::where('id',$user['id'])->update(['invite_img'=>$img]);
//        }else{
//        }
        $img = $host.'/'.$img;
        // 返回 base64 格式的图片
        //$dataUri = $result->getDataUri();
        //echo "<img src='{$dataUri}'>";
        $data=[
            'invite_code' => $user['invite_code'],
            'url'=>dbconfig('ios_download_url'),
            'apk_url' => dbconfig('apk_download_url'),
            'download_url' => dbconfig('download_url'),
//            'img'=>$img,
        ];
        return out($data);
    }

/*     public function hongbao(){
        $user = $this->user;
        $zg = UserRelation::where('user_id',$user['id'])->where('level',1)->where('is_active',1)->select();
        $data = [];
        if(count($zg) >= 10){
            $data['zg'] = 1;
        }else{
            $data['zg'] = 0;
        }
        $sql = 'select user_id from mp_user_relation where is_active=1 and level=1 GROUP BY user_id having count(user_id)>=10';
        $u = Db::query($sql);
        // $data['amount'] = round(100000000 / count($u),2);
        $d = [
            '20221205'=>'225891.28',
            '20221206'=>'236156.19',
            '20221207'=>'278912.01',
            '20221208'=>'300007.22',
            '20221209'=>'326517.59',
            '20221210'=>'353027.95',
            '20221211'=>'379538.32',
            '20221212'=>'406048.68',
            '20221213'=>'432559.05',
            '20221214'=>'459069.41',
            '20221215'=>'485579.78',
            '20221216'=>'512090.14',
            '20221217'=>'538600.51',
            '20221218'=>'565110.87',
            '20221219'=>'591621.24',
            '20221220'=>'618131.60',
            '20221221'=>'644641.97',
            '20221222'=>'671152.33',
            '20221223'=>'697662.70',
            '20221224'=>'724173.06',
            '20221225'=>'750683.43',
            '20221226'=>'777193.79',
            '20221227'=>'803704.16',
            '20221228'=>'830214.52',
            '20221229'=>'856724.89',
            '20221230'=>'883235.25',
            '20221231'=>'909745.62',
            '20220101'=>'936255.98',
            '20220102'=>'962766.35',
            '20220103'=>'989276.71',
            '20220104'=>'1015787.08',
            '20220105'=>'1042297.44',
            '20220106'=>'1068807.81',
            '20220107'=>'1095318.17',
            '20220108'=>'1121828.54',
            '20220109'=>'1148338.90',
            '20220110'=>'1174849.27',
            '20220111'=>'1201359.63',
            '20220112'=>'1227870.00',
            '20220113'=>'1254380.36',
            '20220114'=>'1280890.73',
            '20220115'=>'1307401.09',
            '20220116'=>'1333911.46',
            '20220117'=>'1360421.82',
            '20220118'=>'1386932.19',
            '20220119'=>'1413442.55',
            '20220120'=>'1439952.92',
            '20220121'=>'1466463.28',
            '20220122'=>'1492973.65',
            '20220123'=>'1519484.01'];
        $data['amount'] = $d[date('Ymd')];
        if(!empty($u)){
            $uid = [];
            foreach($u as $v){
                $uid[] = $v['user_id'];
            }
           $phone = User::whereIn('id',$uid)->field('phone,realname')->select();
           foreach($phone as $v){
                $q = substr($v['phone'],0,3);
                $h = substr($v['phone'],7,10);
                $qq = mb_substr($v['realname'],0,1);
                $hh = mb_substr($v['realname'],2);
                $data['list'][] = $q .'****' . $h .'  '.$qq.'*'.$hh;
           }
        }
        return out($data);

    } */

    public function wallet(){
        $user = $this->user;
        $umodel = new User();
        //$user['invite_bonus'] = $umodel->getInviteBonus(0,$user);
        $user['total_balance'] = bcadd($user['topup_balance'],$user['balance'],2);
        $map = config('map.user_balance_log')['type_map'];
        $list = UserBalanceLog::where('user_id',$user['id'])
        ->where('log_type',1)->whereIn('type',[1,2,18,19,30,31,32,302])
        ->order('created_at','desc')
        ->paginate(10)
        ->each(function($item,$key) use ($map){
            $typeText = $map[$item['type']];
            $item['type_text'] = $typeText;
            if($item['type']==3){
                $projectName = Order::where('id',$item['relation_id'])->value('project_name');
                $item['type_text']=$typeText.$projectName;
            }
            return $item;
        });
        $u=[
            'topup_balance'=>$user['topup_balance'],
            'total_balance'=>$user['total_balance'],
            'balance'=>$user['balance'],
        ];
        $data['wallet']=$u;
        $data['list'] = $list;
        return out($data);
    }

    //数字人民币转账
    public function transferAccounts(){
        $req = $this->validate(request(), [
            // 'type' => 'require|in:1,2,3',//1数字人民币,2 现金充值余额 3 可提现余额
            // 'realname|对方姓名' => 'max:20',
            'account|对方账号' => 'require',//虚拟币钱包地址
            'money|转账金额' => 'require|float',
            'pay_password|支付密码' => 'require',
        ]);//type 1 数字人民币，realname 对方姓名，account 对方账号，money 转账金额，pay_password 支付密码
        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        // if (!in_array($req['type'], [1,2,3])) {
        //     return out(null, 10001, '不支持该支付方式');
        // }
        if ($user['phone'] == $req['account']) {
            return out(null, 10001, '不能转帐给自己');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额） 2 转账余额（充值金额加他人转账的金额）
            //topup_balance充值余额 can_withdraw_balance可提现余额  balance总余额
            $take = User::where('phone',$req['account'])->lock(true)->find();//收款人

            if (!$take) {
                exit_out(null, 10002, '用户不存在');
            }
            // if (empty($take['ic_number'])) {
            //     exit_out(null, 10002, '请收款用户先完成实名认证');
            // }
            
            //$field = 'topup_balance';
            $fieldText = '可消费现金余额';
            $logType = 1;


            if ($req['money'] > ($user['topup_balance'] + $user['balance'] + $user['release_balance'])) {
                exit_out(null, 10002, '转账余额不足');
            }
            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];
            

            //2 转账余额（充值金额加他人转账的金额）
            //User::where('id', $user['id'])->inc('balance', $change_balance)->inc($field, $change_balance)->update();

            if($user['topup_balance'] >= $req['money']) {
                User::where('id', $user['id'])->dec('topup_balance', $req['money'])->update();
                UserBalanceLog::create([
                    'user_id' => $user['id'],
                    'type' => 18,
                    'log_type' => $logType,
                    'relation_id' => $take['id'],
                    'before_balance' => $user['topup_balance'],
                    'change_balance' => $change_balance,
                    'after_balance' =>  $user['topup_balance']-$req['money'],
                    'remark' => '转账'.$fieldText.'转账给'.$take['phone'],
                    'admin_user_id' => 0,
                    'status' => 2,
                    'project_name' => ''
                ]);
            } else {
                User::where('id', $user['id'])->dec('topup_balance', $user['topup_balance'])->update();
                UserBalanceLog::create([
                    'user_id' => $user['id'],
                    'type' => 18,
                    'log_type' => $logType,
                    'relation_id' => $take['id'],
                    'before_balance' => $user['topup_balance'],
                    'change_balance' => $change_balance,
                    'after_balance' =>  $user['topup_balance']-$user['topup_balance'],
                    'remark' => '转账'.$fieldText.'转账给'.$take['phone'],
                    'admin_user_id' => 0,
                    'status' => 2,
                    'project_name' => ''
                ]);
                $topup_amount = $req['money'] - $user['topup_balance'];
                if($user['balance'] >= $topup_amount) {
                    User::where('id', $user['id'])->dec('balance', $topup_amount)->update();
                    UserBalanceLog::create([
                        'user_id' => $user['id'],
                        'type' => 18,
                        'log_type' => $logType,
                        'relation_id' => $take['id'],
                        'before_balance' => $user['balance'],
                        'change_balance' => $change_balance,
                        'after_balance' =>  $user['balance']-$topup_amount,
                        'remark' => '转账可提现现金余额给'.$take['phone'],
                        'admin_user_id' => 0,
                        'status' => 2,
                        'project_name' => ''
                    ]);
                } else {
                    User::where('id', $user['id'])->dec('balance', $user['balance'])->update();
                    UserBalanceLog::create([
                        'user_id' => $user['id'],
                        'type' => 18,
                        'log_type' => $logType,
                        'relation_id' => $take['id'],
                        'before_balance' => $user['balance'],
                        'change_balance' => $change_balance,
                        'after_balance' =>  $user['balance']-$user['balance'],
                        'remark' => '转账可提现现金余额给'.$take['phone'],
                        'admin_user_id' => 0,
                        'status' => 2,
                        'project_name' => ''
                    ]);
                    $balance_amount = $topup_amount - $user['balance'];
                    User::where('id', $user['id'])->dec('release_balance', $balance_amount)->update();
                    UserBalanceLog::create([
                        'user_id' => $user['id'],
                        'type' => 18,
                        'log_type' => $logType,
                        'relation_id' => $take['id'],
                        'before_balance' => $user['release_balance'],
                        'change_balance' => $change_balance,
                        'after_balance' =>  $user['release_balance']-$balance_amount,
                        'remark' => '转账释放提款额度给'.$take['phone'],
                        'admin_user_id' => 0,
                        'status' => 2,
                        'project_name' => ''
                    ]);
                }

            }
            
            //User::where('id', $user['id'])->inc($field, $change_balance)->update();
            //User::changeBalance($user['id'], $change_balance, 18, 0, 1,'转账余额转账给'.$take['realname']);
            //增加资金明细


            //收到金额  加金额 转账金额
            //User::where('id', $take['id'])->inc('balance', $req['money'])->inc('topup_balance', $req['money'])->update();
            $field2 = 'topup_balance';
            User::where('id', $take['id'])->inc($field2, $req['money'])->update();
            //User::changeBalance($take['id'], $req['money'], 18, 0, 1,'接收转账来自'.$user['realname']);
            UserBalanceLog::create([
                'user_id' => $take['id'],
                'type' => 19,
                'log_type' => $logType,
                'relation_id' => $user['id'],
                'before_balance' => $take[$field2],
                'change_balance' => $req['money'],
                'after_balance' =>  $take[$field2]+$req['money'],
                'remark' => '接收'.$fieldText.'来自'.$user['phone'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => ''
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    
    //转账2
    public function transferAccounts2(){
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2,3',//1推荐给奖励,2 转账余额（充值金额）3 可提现余额
            //'realname|对方姓名' => 'require|max:20',
            'account|对方账号' => 'require',//虚拟币钱包地址
            'money|转账金额' => 'require|number|between:100,100000',
            'pay_password|支付密码' => 'require',
        ]);//type 1 数字人民币，，realname 对方姓名，account 对方账号，money 转账金额，pay_password 支付密码
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        if (!in_array($req['type'], [1,2,3])) {
            return out(null, 10001, '不支持该支付方式');
        }
        if ($user['phone'] == $req['account'] && $req['type']==2) {
            return out(null, 10001, '不能转帐给自己');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额） 2 转账余额（充值金额加他人转账的金额）
            //topup_balance充值余额 can_withdraw_balance可提现余额  balance总余额
            $user = User::where('id', $user['id'])->lock(true)->find();//转账人
            $wallet =WalletAddress::where('address',$req['account'])->where('user_id','>',0)->find();
            if(!$wallet){
                exit_out(null, 10002, '目标地址不存在');
            }
            $take = User::where('id', $wallet['user_id'])->lock(true)->find();//收款人
            if (!$take) {
                exit_out(null, 10002, '用户不存在');
            }
            if (empty($take['ic_number'])) {
                exit_out(null, 10002, '请收款用户先完成实名认证');
            }
            
            if($req['type'] ==1){
                $field = 'digital_yuan_amount';
                $fieldText = '数字人民币';
                $logType=2;
            }/* elseif($req['type'] ==2){
                $field = 'balance';
                $fieldText = '充值余额';
                $logType = 1;
            } */
            // }else{
            //     $field = 'balance';
            //     $fieldText = '可提现余额';
            // }


            if ($req['money'] > $user[$field]) {
                exit_out(null, 10002, '转账余额不足');
            }
            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];
            

            //2 转账余额（充值金额加他人转账的金额）
            //User::where('id', $user['id'])->inc('balance', $change_balance)->inc($field, $change_balance)->update();
            User::where('id', $user['id'])->inc($field, $change_balance)->update();
            //User::changeBalance($user['id'], $change_balance, 18, 0, 1,'转账余额转账给'.$take['realname']);
            //增加资金明细
            UserBalanceLog::create([
                'user_id' => $user['id'],
                'type' => 18,
                'log_type' => $logType,
                'relation_id' => $take['id'],
                'before_balance' => $user[$field],
                'change_balance' => $change_balance,
                'after_balance' =>  $user[$field]-$req['money'],
                'remark' => '转账'.$fieldText.'转账给'.$take['realname'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => ''
            ]);

            //收到金额  加金额 转账金额
            //User::where('id', $take['id'])->inc('balance', $req['money'])->inc('topup_balance', $req['money'])->update();
            User::where('id', $take['id'])->inc('balance', $req['money'])->update();
            //User::changeBalance($take['id'], $req['money'], 18, 0, 1,'接收转账来自'.$user['realname']);
            UserBalanceLog::create([
                'user_id' => $take['id'],
                'type' => 19,
                'log_type' => 1,
                'relation_id' => $user['id'],
                'before_balance' => $take[$field],
                'change_balance' => $req['money'],
                'after_balance' =>  $take[$field]+$req['money'],
                'remark' => '接收'.$fieldText.'来自'.$user['realname'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => ''
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //余额转账  余额转余额
    public function balanceTransfer(){
        $req = $this->validate(request(), [
            'type'             => 'require',  //1 现金转现金  2团队奖励转现金
            'realname|对方姓名' => 'require|max:20', //姓名
            'account|对方账号'  => 'require|max:11', //手机号
            'money|转账金额'    => 'require|number|between:100,100000', //转账金额
            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

//        $count = FamilyChild::where('user_id',$user['id'])->count();
//        if (!$count) {
//            return out(null, 10001, '请先完成实名认证');
//        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
//        if ($req['type'] != 2) {
//            return out(null, 10001, '不支持该支付方式');
//        }

        if ($user['phone'] == $req['account'] ) {
            return out(null, 10001, '不能转帐给自己');
        }

        if($req['type']==1){
            $field = 'topup_balance';//现金余额
        }elseif($req['type']==2){
            $field = 'xuanchuan_balance';//团队奖励
        }elseif($req['type']==3){
            $field = 'team_bonus_balance';//月薪钱包
        }
        Db::startTrans();
        try {
            $transferUser =  User::where('id', $user['id'])->lock(true)->find();//转账人

            $wallet = User::where('phone',$req['account'])->where('realname',$req['realname'])->find();
            if(!$wallet){
                exit_out(null, 10002, '没找到转账人');
            }

            $take = User::where('id', $wallet['id'])->lock(true)->find();//收款人

            if (!$take) {
                exit_out(null, 10002, '用户不存在');
            }

//            if (empty($take['ic_number'])) {
//                exit_out(null, 10002, '请收款用户先完成实名认证');
//            }

            if ($req['money'] > $transferUser[$field]) {
                exit_out(null, 10002, '余额不足');
            }

            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];

            //2 转账余额
            User::where('id', $transferUser['id'])->inc($field, $change_balance)->update();

            //增加资金明细（当前用户余额 - 转账钱数）
            UserBalanceLog::create([
                'user_id' => $transferUser['id'],
                'type' => 18,
                'log_type' => 1,
                'wallet_type'=>$req['type'],
                'relation_id' => $take['id'],
                'before_balance' => $transferUser[$field],
                'change_balance' => $change_balance,
                'after_balance' =>  $transferUser[$field]-$req['money'],
                'remark' => '转账给'.$take['realname'].$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);

            //收到金额  加金额 转账金额
            $data = [
                'topup_balance' => $wallet['topup_balance'] + $req['money']
            ];
            User::where('id', $wallet['id'])->update($data);
            UserBalanceLog::create([
                'user_id' => $take['id'],
                'type' => 19,
                'log_type' => 1,
                'wallet_type'=>$req['type'],
                'relation_id' => $user['id'],
                'before_balance' => $wallet['topup_balance'],
                'change_balance' => $req['money'],
                'after_balance' => $wallet['topup_balance']+$req['money'],
                'remark' => '来自'.$user['realname'].'的转账给'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //宣传转账（宣传转余额）
    public function promotionTransfer(){
        $req = $this->validate(request(), [
//            'type'             => 'require',  //4 转账余额（充值金额）
            'realname|对方姓名' => 'require|max:20', //姓名
            'account|对方账号'  => 'require|max:11', //手机号
            'money|转账金额'    => 'require|number|between:100,100000', //转账金额
//            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

//        $count = FamilyChild::where('user_id',$user['id'])->count();
//        if (!$count) {
//            return out(null, 10001, '请先完成实名认证');
//        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
//        if ($req['type'] != 4) {
//            return out(null, 10001, '不支持该支付方式');
//        }
//        if ($user['phone'] == $req['account']) {
//            return out(null, 10001, '不能转帐给自己'); 
//        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额）
            $user = User::where('id', $user['id'])->lock(true)->find();//转账人
            $wallet = User::where('phone',$req['account'])->where('realname',$req['realname'])->find();

            if(!$wallet){
                exit_out(null, 100023, '目标用户不存在');
            }

            $take = User::where('id', $wallet['id'])->lock(true)->find();//收款人
            if (!$take) {
                exit_out(null, 100025, '用户不存在');
            }

//            if (empty($take['ic_number'])) {
//                exit_out(null, 10002, '请收款用户先完成实名认证');
//            }
            

            if ($req['money'] > $user['xuanchuan_balance']) {
                exit_out(null, 10002, '余额不足');
            }
            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];

            //2 宣传
            User::where('id', $user['id'])->inc('xuanchuan_balance', $change_balance)->update();

            //扣除
            UserBalanceLog::create([
                'user_id' => $user['id'],
                'type' => 18,
                'log_type' => 4,
                'relation_id' => $take['id'],
                'before_balance' => $user['xuanchuan_balance'],
                'change_balance' => $change_balance,
                'after_balance' =>  $user['xuanchuan_balance']-$req['money'],
                'remark' => '转账给'.$wallet['realname'].$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);

            //增加
            User::where('id', $take['id'])->inc('topup_balance', $req['money'])->update();
            UserBalanceLog::create([
                'user_id' => $wallet['id'],
                'type' => 19,
                'log_type' => 1,
                'relation_id' => $user['id'],
                'before_balance' => $take['topup_balance'],
                'change_balance' => $req['money'],
                'after_balance' =>  $take['topup_balance']+$req['money'],
                'remark' => '来自'.$user['realname'].'的转账'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function transferList()
    {
        $req = $this->validate(request(), [
            //'status' => 'number',
            //'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [18,19])->order('created_at','desc')
                    ->paginate(10,false,['query'=>request()->param()]);
        if($builder){
            foreach($builder as $k => $v){
                $builder[$k]['phone'] = User::where('id', $v['relation_id'])->value('phone');
            } 
        }    
        
        return out($builder);
    }

    public function submitProfile()
    {
        $req = $this->validate(request(), [
            'realname|真实姓名' => 'require',
            'ic_number|身份证号' => 'require|idCard',
        ]);
        $userToken = $this->user;
        $redis = new \Predis\Client(config('cache.stores.redis'));
        $ret = $redis->set('profile_'.$userToken['id'],1,'EX',10,'NX');
        if(!$ret){
            return out("服务繁忙，请稍后再试");
        }
        //\think\facade\Log::debug('submitProfile method start.');
        \think\facade\Log::debug('Request validated'.json_encode(['request' => $req,'user_id'=>$userToken['id']],JSON_UNESCAPED_SLASHES));
        Db::startTrans();
        try{
            $user = User::where('id',$userToken['id'])->find();
            
            if ($user['ic_number']!='') {
                return out(null, 10001, '您已经实名认证了');
            }
            if($user['realname']!=''){
                return out(null, 10001, '您已经实名认证了');
            }

            if (User::where('ic_number', $req['ic_number'])->count()) {
                return out(null, 10001, '该身份证号已经实名过了');
            }
            \think\facade\Log::debug('User not verified.'.json_encode(['user_id' => $user['id'],'realname'=>$user['realname'],'ic_number'=>$user['ic_number']],JSON_UNESCAPED_SLASHES));

            User::where('id', $user['id'])->update($req);

            //注册赠送100万数字人民币
            if($user['is_realname']==0){
                User::changeInc($user['id'], 1000000,'digital_yuan_amount',24,0,3,'注册赠送数字人民币',0,1,'SM');
            }
            Db::commit();

        }catch(\Exception $e){
            \think\facade\Log::debug('Error in submitProfile method.'. $e->getMessage());
            Db::rollback();
            return out(null,10012,$e->getMessage());
        }
        //\think\facade\Log::debug('submitProfile method completed.');
        
        
        // 给直属上级额外奖励
/*         if (!empty($user['up_user_id'])) {
            User::changeBalance($user['up_user_id'], dbconfig('direct_recommend_reward_amount'), 7, $user['id']);
        } */

        // // 把注册赠送的股权给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 1)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);
        
        //         // 把注册赠送的数字人民币给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 2)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        // // 把注册赠送的贫困补助金给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 3)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        return out();
    }

    public function changePassword()
    {
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2',
            'new_password|新密码' => 'require|alphaNum|length:6,12',
            'old_password|原密码' => 'requireIf:type,1',
        ]);
        $user = $this->user;

        if ($req['type'] == 2 && !empty($user['pay_password']) && empty($req['old_password'])) {
            return out(null, 10001, '原密码不能为空');
        }

        // if ($req['type'] == 2 && empty($user['ic_number'])) {
        //     return out(null, 10002, '请先进行实名认证');
        // }

        $field = $req['type'] == 1 ? 'password' : 'pay_password';
        // if (!empty($user['pay_password']) && !empty($req['old_password']) && $user[$field] !== sha1(md5($req['old_password']))) {
        //     return out(null, 10003, '原密码错误');
        // }
        if (!empty($user[$field]) && $user[$field] !== sha1(md5($req['old_password']))) {
            return out(null, 10003, '原密码错误');
        }

        User::where('id', $user['id'])->update([$field => sha1(md5($req['new_password']))]);

        return out();
    }

/*     public function userBalanceLog()
    {
        $req = $this->validate(request(), [
            'log_type' => 'require|in:1,2,3,4',
            'type' => 'number',
        ]);
        $user = $this->user;

        $builder = UserBalanceLog::where('user_id', $user['id'])->where('log_type', $req['log_type']);
        if (!empty($req['type'])) {
            $builder->where('type', $req['type']);
        }
        $data = $builder->order('id', 'desc')->paginate();

        return out($data);
    }
 */

    public function team(Request $request)
    {
        $user = $this->user;
        $active_cn = ['未激活','已激活'];
        $level = $request->param('level');
        $where[] = ['a.user_id', '=',$user['id']];
        $where[] = ['a.level', '=', $level];

        $where1[] = ['user_id', '=',$user['id']];
        $where1[] = ['level', '=', $level];
        $pageSize = $request->param('pageSize', 10, 'intval');
        $pageNum = $request->param('pageNum', 1);
//        $data['list'] = UserRelation::with('subUser')->where($where)->page($pageNum, $pageSize)->select()->each(function ($item) use ($active_cn) {
//            $item['is_active_cn'] = $active_cn[$item['is_active']];
//        });
//        $data['list'] = [];

        $data['list'] = Db::table('mp_user_relation')->alias('a')->leftJoin('mp_user b','a.sub_user_id = b.id')->where($where)->page($pageNum, $pageSize)->select();

        $data['total_num'] = UserRelation::where($where1)->count();
        $data['total_receive_num'] = UserRelation::where($where1)->count();

        //上一级名
        $data['up_user_name'] = "没有上级";
        if ($user['up_user_id']>0){
            $up_info = User::where('id',$user['up_user_id'])->find();
            $data['up_user_name'] = empty($up_info['realname'])?$up_info['phone']:$up_info['realname'];
        }

        $arr = [1,2,3];
        foreach ($arr as $v){
            $level = 'level'.$v;
            $active = 'active'.$v;
            $count = UserRelation::where('user_id', $user['id'])->where('level', $v)->count();
            $count1 = UserRelation::where('user_id', $user['id'])->where('level', $v)->where('is_active',1)->count();
            $data[$level] = $count;
            $data[$active] = $count1;
        }

        return out($data);
    }

    public function inviteBonus(){
        $user = $this->user;
        $invite_bonus = UserBalanceLog::alias('l')->join('mp_order o','l.relation_id=o.id')
                                                ->field('l.created_at,l.type,l.remark,change_balance,single_amount,buy_num,project_name,o.user_id')
                                                ->where('l.type',9)
                                                ->where('l.user_id',$user['id'])
                                                ->order('l.created_at','desc')
                                                ->limit(10)
                                                //->fetchSql(true)
                                                ->paginate();
        foreach($invite_bonus as $key=>$item){
        
            $orderPrice = bcmul($item['single_amount'],$item['buy_num'],2);
            $realname = User::where($item['user_id'])->value('realname');
            $invite_bonus[$key]['realname'] = $realname;
            $level = UserRelation::where('user_id',$user['id'])->where('sub_user_id',$item['user_id'])->value('level');
            $levelText = [
                '1'=>"一级",
                '2'=>'二级',
                '3'=>'三级',
            ];
            if($item['type'] == 8){
                $remark = $item['remark'];
            }elseif($item['type'] == 9){
                $remark = $item['remark'];
            }else{
                $remark = '奖励';
            }
            $invite_bonus[$key]['text'] = "推荐{$levelText[$level]}用户 $realname 投资 $orderPrice ,{$remark} {$item['change_balance']} ";

        }                                     
        $data['list'] = $invite_bonus;
        return out($data);
    }

    public function teamRankList()
    {   //1
        $req = $this->validate(request(), [
            'level' => 'require|in:1,2,3',
        ]);
        $user = $this->user;
        //查找我的下级用户有多少个
//        $total_num = UserRelation::where('user_id', $user['id'])->where('level', 1)->count();
//        $total_num = UserRelation::alias('r') // 给 mp_user_relation 表起别名 r
//                ->join('mp_user_balance_log b', 'r.sub_user_id = b.user_id') // 关联用户资金日志表
//                ->where('r.user_id',  $user['id'])  // 查找 user_id =  的所有下级用户
//                ->where('r.level',  1)
//                ->where('b.type', 57)  // 在资金日志表中查找 type = 57 的记录
//                ->count();  // 统计条数
        $total_num = UserBalanceLog::where('user_id',$user['id'])->where('type',57)->count();
        //查找我的下级激活用户有多少个
        $active_num = UserRelation::where('user_id', $user['id'])->where('level', $req['level'])
                                  ->where('is_active', 1)->count();
        //查找我的下级实名用户有多少个
        $realname_num = UserRelation::alias('r')->join('mp_user u','r.user_id = u.id')
                                  ->where('user_id',$user['id'])->where('r.level', $req['level'])->where('u.realname','<>','')->count();

        $total_money = UserBalanceLog::where('user_id',$user['id'])->where('type',57)->sum('change_balance');

        $list = UserRelation::where('user_id', $user['id'])->where('level', $req['level'])
                            ->field('sub_user_id')->paginate(50);
        if ($list->items()) {
            foreach ($list as $k => $v) {
                // 获取每个下级用户的详细信息
                $user = User::field('id, avatar, phone, realname, invite_bonus, invest_amount, equity_amount, level, is_active, created_at')
                    ->where('id', $v['sub_user_id'])
                    ->find();

                if ($user) {  // 确保 $user 存在
                    $user['phone'] = substr_replace($user['phone'], '****', 3, 4);

                    if ($user['realname'] != '') {
                        // 隐藏真实姓名的中间部分
                        $user['realname'] = mb_substr($user['realname'], 0, 1) . "*" . mb_substr($user['realname'], 2);
                    }
                    $list[$k] = $user; // 替换原来的值为处理后的用户数据
                } else {
                    // 如果查询的用户为空，可以设置一个默认值或跳过
                    $list[$k] = [
                        'id' => 0,
                        'avatar' => '',
                        'phone' => '未知',
                        'realname' => '未知',
                        'invite_bonus' => 0,
                        'invest_amount' => 0,
                        'equity_amount' => 0,
                        'level' => 0,
                        'is_active' => 0,
                        'created_at' => '',
                    ];
                }
            }
        }
        
        // $list = User::field('id,avatar,phone,invest_amount,equity_amount,level,is_active,created_at')->whereIn('id', $sub_user_ids)->order('equity_amount', 'desc')->paginate();
//        $total_money = calculateTotalReward($total_num);

        return out([
            'total_num' => $total_num,
            'total_money' => $total_money,
            'receive_num'=> $active_num,
            'realname_num'=> $realname_num,
            'list' => $list,
        ]);
    }

    public function teamLotteryConfig()
    {
        $proArr = [
            array('id' => 1, 'name' => 66, 'v' => 1),
            array('id' => 2, 'name' => 88, 'v' => 5),
            array('id' => 3, 'name' => 99, 'v' => 10),
            array('id' => 4, 'name' => 128, 'v' => 12),
            array('id' => 5, 'name' => 188, 'v' => 50),
        ];
        return out($proArr);
    }

    public function teamLottery()
    {
        $user = $this->user;
        $proArr = [
            array('id' => 1, 'name' => 66, 'v' => 1),
            array('id' => 2, 'name' => 88, 'v' => 5),
            array('id' => 3, 'name' => 99, 'v' => 10),
            array('id' => 4, 'name' => 128, 'v' => 12),
            array('id' => 5, 'name' => 188, 'v' => 50),
        ];

        if($user['lottery_times']  < 1) {
            return out(null, 10001, '请先推荐一人注册并完成提交');
        }

        $result = array();
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
        User::changeInc($user['id'],$result['name'],'digital_yuan_amount',39,0,3);
        User::where('id', $user['id'])->dec('lottery_times', 1)->update();

        return out($result);
    }


    public function payChannelList()
    {
        $req = $this->validate(request(), [
            //'type' => 'require|number|in:1,2,3,4,5',
        ]);
        $user = $this->user;
        $userModel = new User();
        $toupTotal = $userModel->getTotalTopupAmountAttr(0,$user);
        $data = [];
/*         foreach (config('map.payment_config.channel_map') as $k => $v) {
            //$paymentConfig = PaymentConfig::where('type', $req['type'])->where('status', 1)->where('channel', $k)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
            $paymentConfig = PaymentConfig::where('status', 1)->where('channel', $k)->where('start_topup_limit', '<=', $toupTotal)->order('start_topup_limit', 'desc')->find();
            if (!empty($paymentConfig)) {
                //$confs = PaymentConfig::where('type', $req['type'])->where('status', 1)->where('channel', $k)->where('start_topup_limit', $paymentConfig['start_topup_limit'])->select()->toArray();
                $confs = PaymentConfig::where('status', 1)->where('channel', $k)->where('start_topup_limit', $paymentConfig['start_topup_limit'])->select()->toArray();
                $data = array_merge($data, $confs);
            }
        } */
        $data = PaymentConfig::Where('status',1)->where('start_topup_limit', '<=', $toupTotal)->order('sort desc')->select();
        $img =[1=>'wechat.png',2=>'alipay.png',3=>'unionpay.png',4=>'unionpay.png',5=>'unionpay.png',6=>'unionpay.png',7=>'unionpay.png',8=>'unionpay.png',];
        foreach($data as &$item){
            $item['img'] = env('app.img_host').'/storage/pay_img/'.$img[$item['type']];
            if($item['type']==4){
                $item['type'] = 6;
            }else{
                $item['type'] = $item['type']+1;
            }
           
        }

        return out($data);
    }

    public function payList(){

    }
    public function klineTotal()
    {
        $k = KlineChartNew::where('date',date("Y-m-d",strtotime("-1 day")))->field('price25')->order('id desc')->find();
        $data['klineTotal'] = $k['price25'];
        return out($data);
    }

    public function allBalanceLog()
    {
        $map = config('map.user_balance_log')['type_map'];
        $list = UserBalanceLog::order('created_at', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            if ($item['type'] == 6) {
                $projectName = Order::where('id', $item['relation_id'])->value('project_name');
                $item['type_text'] = $projectName.'分配额度';
            }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['created_at']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
    }
    
    public function balanceLog()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'type'     => 'number',
//            'log_type' => 'number',
        ]);

        $map = config('map.user_balance_log')['type_map'];
       // $log_type = $req['log_type'];
        $obj = UserBalanceLog::where('user_id', $user['id']);
//        if(isset($req['log_type'])) {
//            if($req['log_type'] == 1){
//                $obj = $obj->whereIn('log_type',[$req['log_type'],6]);
//            } else {
//                $obj = $obj->where('log_type',$req['log_type']);
//            }
//        }
        if (request()->has('month')) {
            $time = request()->param('month');// 获取前端传递的 time 参数

            if (preg_match('/^\d{4}-\d{2}$/', $time)) { // 确保格式是 YYYY-MM
                $startDate = $time . '-01 00:00:00'; // 该月的第一天
                $endDate = date('Y-m-t 23:59:59', strtotime($startDate)); // 该月的最后一天
                $obj = $obj->whereBetween('created_at', [$startDate, $endDate]);
            }
        }
        if(isset($req['type'])) {
            switch ($req['type']) {
                case 1://现金钱包  —充值金额  领取每日现金红包 3购买项目 15后台转入 8团队奖励 58会议签到奖励4元 37HVPS开户 100 现金转红包 101来自现金钱包转入  53线上会议参会奖励',54线上会议邀请奖励',
                    $arr = [1,3, 35,45,15,8,58,37,100,101,18,19,53,54,27];
                    $obj = $obj->whereIn('type', $arr);
                    break;
                case 2://收益钱包  —项目产品的收益
                    $arr = [12,22 ,36,39];//12项目返还投资款 36购买产品后 钱包收益 22领取礼品
                    $obj = $obj->whereIn('type', $arr);
                    break;
                case 3://福利钱包  —17签到奖励 和 56抽奖 57贡献奖励 26退回HVPS开户费 28银行卡授信签约费退还
                    $arr = [17, 56 ,57,26,28,59,38];
                    $obj = $obj->whereIn('type', $arr);
                    break;
                case 4://月薪钱包  —团队长工资 48, 55月薪钱包扣款
                    $arr = [48,55];//没确定
                    $obj = $obj->whereIn('type', $arr);
                    break;

//            $obj = $obj->where('type',$req['type']);
            }
        }
        //->where('log_type', $log_type)
        $list = $obj->order('id', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            // if ($item['type'] == 6) {
            //     $projectName = Order::where('id', $item['relation_id'])->value('project_name');
            //     $item['type_text'] = $projectName.'分配额度';
            // }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['id']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
       
    }

    public function balanceLogTrans()
    {
        $user = $this->user;
        $data = UserBalanceLog::where('user_id',$user['id'])->where('order_sn',999999999)->whereIn('log_type',[1,4])->select();
        return out($data);
    }



    public function balanceLogBank()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            //'type' => 'require|number|in:1,2,3,4,5',
            //充值 1  团队奖励2  3国家津贴  6收益
            // 'log_type' => 'require|number|in:1,2,3,6',
        ]);
        $map = config('map.user_balance_log')['type_map'];
       // $log_type = $req['log_type'];
        $list = UserBalanceLog::where('user_id', $user['id'])
        ->whereIn('type', [52,53,54,55,59,62, 64, 67,69])
        ->order('id', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            if ($item['type'] == 6) {
                $projectName = Order::where('id', $item['relation_id'])->value('project_name');
                $item['type_text'] = $projectName.'分配额度';
            }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['id']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
       
    }

    public function certificateList(){
        $user = $this->user;
        $list = Certificate::where('user_id',$user['id'])->order('id','desc')->select();
        foreach($list as $k=>&$v){
           $v['format_time']=Certificate::getFormatTime($v['created_at']);
        }
        return out($list);
    }

    public function certificate(){
        $req = $this->validate(request(), [
            'id|id' => 'integer',
            'project_group_id|组ID' => 'integer',
        ]);
        if(!isset($req['id']) && !isset($req['project_group_id'])){
            return out('参数错误');
        }
        $query = Certificate::order('id','desc');
        if(isset($req['id'])){
            $query->where('id',$req['id']);
        }else if(isset($req['project_group_id'])){
            $query->where('project_group_id',$req['project_group_id']);
        }
        $certificate = $query->find();
        if(!$certificate){
            return out([],10001,'证书不存在');
        }
        $certificate['format_time']=Certificate::getFormatTime($certificate['created_at']);
        return out($certificate);
    }

    public function saveUserInfo(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'qq|QQ' => 'min:5',
            'address|地址' => 'min:4',
        ]);
        if((!isset($req['qq']) || trim($req['qq'])=='') && (!isset($req['address']) || trim($req['address'])=='')){
            return out(null,10010,'请填写对应字段');
        }
        if(isset($req['address']) && $req['address']!=''){
            UserDelivery::updateAddress($user,['address'=>$req['address']]);
        }

        if(isset($req['qq']) && $req['qq']!=''){
            User::where('id',$user['id'])->update(['qq'=>$req['qq']]);
        }
        return out();

    }

    public function avatar(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'avatar|头像' => 'require',
        ]);
        User::where('id',$user['id'])->update(['avatar'=>$req['avatar']]);
        return out();
    }

    /**
     * 提交绑定银行卡
     */
    public function bankInfo()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'bank_name|姓名' => 'require',
            'bank_number|银行卡号' => 'require',
        ]);
        User::where('id', $user->id)->data(['bank_name' => $req['bank_name'], 'bank_number' => $req['bank_number']])->update();
        return out();
    }

    /**
     * 实名认证
     */
    public function authentication()
    {
        $user = $this->user;
//        $clickRepeatName = 'authentication-' . $user->id;
//        if (Cache::get($clickRepeatName)) {
//            return out(null, 10001, '操作频繁，请稍后再试');
//        }
//        Cache::set($clickRepeatName, 1, 5);
        $req= $this->validate(request(), [
            'realname|真实姓名'=>['require','regex'=>'/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}·]{2,20}+$/u'],
            'id_card_number|身份证号' => ['require', 'regex' => '/^\d{15}$|^\d{17}[\dXx]$/'],
//            'gender|性别' => 'require|number',
//            'phone|手机号' => 'require|mobile',
//            'card_front|身份证正面照片' => 'require',
//            'card_back|身份证背面照片' => 'require',
//            'card_hand|手持身份证照片' => 'require',
        ]);

//        $card_number_count= User::where('ic_number', $req['id_card_number'])->count();
//        if($card_number_count){
//            return out(null, 10001, '当前身份证号已被注册实名！');
//        }
//        $isAuthentication = Authentication::where('user_id', $user->id)->where('status', 0)->find();
        User::where('id',$user['id'])->update(['realname'=> $req['realname'],'ic_number'=>$req['id_card_number'] ]);

//        $isAuthentication = Authentication::where('user_id', $user->id)->where('status', 0)->find();

//        if ($isAuthentication) {
//            if ($isAuthentication['status'] == 0) {
//                return out(null, 10001, '已提交请等待审核通过');
//            } elseif ($isAuthentication['status'] == 1) {
//                return out(null, 10001, '已通过实名');
//            }
//        }

        $count= Authentication::where('user_id', $user['id'])->count();

        if ($count){
            Authentication::where('user_id', $user->id)->update([
                'realname' => $req['realname'],
                'id_card_number' => $req['id_card_number'] ,
                'gender' => isset($req['gender']) ? $req['gender'] : null,
                'phone' => isset($req['phone']) ? $req['phone'] : $user['phone'],
                'card_front' => isset($req['card_front']) ? $req['card_front'] : null,
                'card_back' => isset($req['card_back']) ? $req['card_back'] : null,
                'card_hand' => isset($req['card_hand']) ? $req['card_hand'] : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            Authentication::insert([
                'user_id' => $user->id,
                'id_card_number' => $req['id_card_number'] ,
                'realname' => $req['realname'],
                'gender' => isset($req['gender']) ? $req['gender'] : null,
                'phone' => isset($req['phone']) ? $req['phone'] :$user['phone'],
                'card_front' => isset($req['card_front']) ? $req['card_front'] : null,
                'card_back' => isset($req['card_back']) ? $req['card_back'] : null,
                'card_hand' => isset($req['card_hand']) ? $req['card_hand'] : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return out(null, 10001, '认证成功');
    }

    public function authenticationStatus(){
        $user = $this->user;

//        $clickRepeatName = 'authenticationStatus-' . $user->id;
//        if (Cache::get($clickRepeatName)) {
//            return out(null, 10004, '操作频繁，请稍后再试');
//        }
//        Cache::set($clickRepeatName, 1, 5);
        if($user['realname']!='' && $user['ic_number']!=''){
            return out(['code' => 1]);//已实名
        }else{
            return out(['code' => 0]);//未实名
        }
//        $isAuthentication = Authentication::where('user_id', $user->id)->find();
//
//        if(!$isAuthentication){
//            return out(['code' => 1]);//code = 1未实名2已提交请等待审核通过3已通过实名4实名未通过，请联系客服
//        }
//        if ($isAuthentication['status'] == 0) {
//            return out(['code' => 2]);//2已提交请等待审核通过
//        } elseif ($isAuthentication['status'] == 1) {
//            return out(['code' => 3]);//3已通过实名
//        }else{
//            return out(['code' => 4]);//4实名未通过，请联系客服
//        }
    }

    /**
     * 收货地址
     */
    public function editUserDelivery()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|收货人名称' => 'require|max:50',
            'phone|手机号' => 'require|max:20',
            'address|详细地址' => 'require|max:250',
        ]);
        $userDeliveryExists = UserDelivery::where('user_id', $user['id'])->find();        
        if ($userDeliveryExists) {
            UserDelivery::where('user_id', $user['id'])->update($req);
        } else {
            $req['user_id'] = $user['id'];
            UserDelivery::create($req);
        }
        return out();
    }

    /**
     * 团队信息
     */
    public function teamInfo()
    {
        $user = $this->user;
        $return = [];
        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
        
        //我的收益
        $return['income'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
        
        //团队流水
        $return['teamFlow'] = UserBalanceLog::whereIn('user_id', $sonIds)->whereIn('type', [3,10])->where('change_balance', '<', 0)->sum('change_balance');

        //团队人数
        $return['teamCount'] = count($sonIds);

        //直推人数
        $return['layer1SonCount'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->count();

        //新增人数
        $return['addCount'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->where('created_at', '>=', date('Y-m-d 00:00:00'))->count();

        //团队余额宝余额
        $return['teamYuEBao'] = Db::name('order')->whereIn('user_id', $sonIds)->where('status', 2)->where('project_group_id', 1)->sum('buy_amount');

        //团队总资产
        $teamAssets = 0;
        foreach ($sonIds as $uid) {
            $teamAssets += $this->assets($uid);
        }
        $return['teamAssets'] = $teamAssets;

        //团队可提现余额
        $return['teamBalance'] = UserBalanceLog::whereIn('user_id', $sonIds)->where('type', 2)->sum('change_balance');

        //二级人数
        $return['layer2SonCount'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('is_active', 1)->count();

        return out($return);
    }

    /**
     * 团队流水列表
     */
    public function teamFlowList()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
        $list = UserBalanceLog::alias('l')->field('l.*, u.phone')->leftJoin('mp_user u', 'u.id = l.user_id')->whereIn('user_id', $sonIds)->whereIn('type', [])->paginate(10);
        return out($list);
    }


    /**
     * 团队人数
     */
    public function layerInfo()
    {
        $user = $this->user;
        //我的上级
        $father = '';
        if (!empty($user['up_user_id'])) {
            $father = User::find($user['up_user_id'])['realname'];
        }
        //一级人数
        $son1Ids = UserRelation::where('user_id', $user['id'])->where('level', 1)->count();
        //二级人数
        $son2Ids = UserRelation::where('user_id', $user['id'])->where('level', 2)->count();
        //三级人数
        $son3Ids = UserRelation::where('user_id', $user['id'])->where('level', 3)->count();
        //推广奖励
        $promoteReward = UserBalanceLog::where('user_id', $user['id'])->where('type', 8)->sum('change_balance');

        return out([
            'father' => $father,
            'layer1Count' => $son1Ids,
            'layer2Count' => $son2Ids,
            'layer3Count' => $son3Ids,
            'promoteReward' => $promoteReward,
        ]);
    }

    /**
     * 团队人数-下级列表
     */
    public function layerInfoSonList()
    {
        $user = $this->user;
        $req = request()->param();
        $data = $this->validate($req, [
            'layer|层级' => 'require|number',
            'pageLimit|条数' => 'number',
        ]);

        $list = UserRelation::field('r.*, u.realname, u.phone')->alias('r')->leftJoin('mp_user u', 'u.id = r.sub_user_id')->where('r.user_id', $user['id'])->where('r.level', $data['layer'])->paginate($data['pageLimit'] ?? 10);
        foreach ($list as $key => $value) {
            $status = '';
            $yuanmeng = YuanmengUser::where('user_id', $value['sub_user_id'])->find();
            $user = User::find($value['sub_user_id']);
            if (empty($yuanmeng)) {
                $status = 'unsigned';
            } else {
                $status = 'signed';
                if ($user['ic_number'] == 1) {
                    $status = 'authed';
                }
            }
            if ($user['is_active'] == 1) {
                $status = 'actived';
            }
            $list[$key]['status'] = $status;
        }
        return out($list);
    }

    /**
     * 一级成员
     */
    public function layer1Son()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->column('sub_user_id');
        $list = User::field('phone, level, created_at')->whereIn('id', $sonIds)->order('id', 'desc')->paginate(10);
        return out($list);
    }

    /**
     * 二级成员
     */
    public function layer2Son()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('is_active', 1)->column('sub_user_id');
        $list = User::field('phone, level, created_at')->whereIn('id', $sonIds)->order('id', 'desc')->paginate(10);
        return out($list);
    }

    //计算资产
    public function assets($userId)
    {
        $user = User::where('id', $userId)->find();
        $assets = $user['balance'] + $user['topup_balance'] + $user['team_bonus_balance'];
        $assets += Db::name('order')->where('user_id', $userId)->where('status', 2)->sum('buy_amount');
        $coin = UserCoinBalance::where('user_id', $userId)->select();
        foreach ($coin as $v) {
            $coin = Coin::where('id', $v['coin_id'])->find();
            $assets += bcmul(Coin::nowPrice($coin['code']), $v['balance'], 4);
        }
        return $assets;
    }

    /**
     * 我的页面
     */
    public function mine()
    {
//        $user = $this->user;
//        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
//        //总资产
//        $return['totalAssets'] = $this->assets($user['id']);
//        //可提现余额
//        $return['balance'] = $user['balance'];
//        //累计个人收益
//        $return['income'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
//        //累计团队收益
//        $return['teamIncome'] = UserBalanceLog::whereIn('user_id', $sonIds)->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
//        //今日收益
//        $return['todayIncome'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->where('created_at', '>', date('Y-m-d 00:00:00'))->sum('change_balance'); 
//
//        //等级
//        $return['level'] = $user['level'];
//        //手机号
//        $return['phone'] = $user['phone'];
//        //邀请码
//        $return['invite_code'] = $user['invite_code'];
//        //认证状态
//        $auth = Authentication::where('user_id', $user['id'])->where('status', 1)->find();
//        if ($auth) {
//            $return['authentication'] = 1;
//        } else {
//            $return['authentication'] = 0;
//        }
//        return out($return);

        $user = $this->user;
        $auth = Authentication::where('user_id', $user['id'])->where('status', 1)->find();
        return out([
            'avatar' => $user['avatar'],
            'level' => $user['level'],
            'balance' => $user['balance'],
            'realname' => $user['realname'],
            'phone' => $user['phone'],
            'invite_code' => $user['invite_code'],
            'topup_balance' => $user['topup_balance'],
            'digital_yuan_amount' => $user['digital_yuan_amount'],
            'poverty_subsidy_amount' => $user['poverty_subsidy_amount'],
            'invite_bonus' => $user['invite_bonus'],
            'income_balance' => $user['income_balance'],
            'xuanchuan_balance' => $user['xuanchuan_balance'],
            'shengyu_balance' => $user['shengyu_balance'],
            'shengyu_butie_balance' => $user['shengyu_butie_balance'],
            'authentication' => ($auth?1:0),
            'yixiaoduizijin' => $user['yixiaoduizijin'],
            'yu_e_bao' => $user['yu_e_bao'],
            'yu_e_bao_shouyi' => $user['yu_e_bao_shouyi'],
            'buzhujin' => $user['buzhujin'],
            'shouyijin' => $user['shouyijin'],
        ]);
    }

    public function message()
    {
        $user = $this->user;
        $messageList = Message::where('to', $user['id'])->order('id', 'desc')->paginate(10);
        return out($messageList);
    }

    public function messageRead()
    {
        $req = $this->validate(request(), [
            'ids|ud' => 'require',
        ]);
        $exp = explode(',', $req['ids']);
        if ($exp && count($exp) > 1) {
            foreach ($exp as $v) {
                Message::where('id', $v)->data(['read' => 1])->update();
            }
        } else {
            Message::where('id', $req['ids'])->data(['read' => 1])->update();
        }
        return out();
    }

    // 房产税 zhufang_order 加 tax字段, hongli area 字段, hongli_order tax字段
    // mp_certificate_trans 表
    // mp_private_transfer_log 表
    public function house_tax()
    {
        $provinces = [
            '北京市',
            '上海市',
            '天津市',
            '江苏省',
            '浙江省',
            '福建省',
            '广东省',
            '香港',
            '澳门',
            '台湾省',
        ];

        $user = $this->user;
        $ids = Hongli::where('area', '>', 0)->column('id');
        $has = HongliOrder::whereIn('hongli_id', $ids)->find();
        $hongli_houses = [];
        if ($has) {
            $address = UserDelivery::where('user_id', $user['id'])->value('address');
            if (!$address) {
                return out(null, 10001, '请先设置收货地址');
            }

            $address_arr = extractAddress($address);

            if (!is_null($address_arr)) {
                $orders = HongliOrder::whereIn('hongli_id', $ids)
                    ->where('user_id', $user['id'])
                    ->field('id, hongli_id, created_at,tax')
                    ->with('hongli')
                    ->order('id', 'desc')
                    ->select()
                    ->each(function($item, $key) use ($address_arr) {
                        $item['pingfang'] = $item->hongli->area ?? null;
                        $item['province_name'] = $address_arr['province'];
                        $item['city_name'] = $address_arr['city'];
                        $item['area'] = $address_arr['area'];
                        return $item;
                    });

                if (!empty($orders)) {
                    foreach($orders as $key => $value) {
                        if (is_null($value['pingfang'])) {
                            continue;
                        }
                        $hongli_houses[] = [
                            'pingfang' => $value['pingfang'],
                            'province_name' => $value['province_name'], 
                            'city_name' => $value['city_name'], 
                            'area' => $value['area'],
                            'type' => 1,
                            'created_at' => $value['created_at'],
                            'tax' => $value['tax'],
                        ];
                    }
                }
            }
        }

        $houses = ZhufangOrder::where('user_id', $user['id'])
            ->field('tax,pingfang,province_name,city_name,area,created_at')
            ->order('id', 'desc')
            ->select()
            ->each(function($item, $key) {
                $item['type'] = 2;
                return $item;
            })
            ->toArray();

        $merge = array_merge($hongli_houses, $houses);
        foreach($merge as $key => $value) {
            if (in_array($value['province_name'], $provinces)) {
                $merge[$key]['house_tax'] = 4000;
            } else {
                $merge[$key]['house_tax'] = 2000;
            }
            $merge[$key]['name'] = $user['realname'];
            $merge[$key]['stamp'] = 2.5;
        }
        return out($merge);
    }

    //开户认证
    public function openAuth(Request $request)
    {
        $req = $this->validate(request(), [
            'id_card_front|身份证正面' => 'require',
            'id_card_opposite|身份证反面' => 'require'
        ]);
        $user = $this->user;
        $apply = Apply::where('user_id',$user['id'])->where('type',5)->find();
        if($apply){
            return out(null,10001,'已经申请过了');
        }
        $data['user_id'] = $user['id'];
        $data['type'] = 5;
        $data['id_card_front'] = $request->param('id_card_front'); //正
        $data['id_card_opposite'] = $request->param('id_card_opposite'); //反
        $data['create_time'] = date('Y-m-d H:i:s');
        Apply::create($data);
        return out(null,200,'申请提交成功');
    }

    //户口认证
    public function householdAuthentication(Request $request)
    {
        $req = $this->validate(request(), [
            'child1|一孩'     => 'require',
        ]);
        $user = $this->user;

        $data['user_id'] = $user['id'];
        $data['type'] = 6;
        //一孩
        $data['child1'] = $req['child1'];
        //二孩
        if ($request->param('child2')){
            $data['child2'] = $request->param('child2');
        }
        //三孩
        if ($request->param('child3')){
            $data['child3'] = $request->param('child3');
        }
        //成员
        if ($request->param('family_members')){
            $data['family_members'] = $request->param('family_members');
        }
        //地址
        if ($request->param('family_address')){
            $data['family_address'] = $request->param('family_address');
        }
        //我
        if ($request->param('my')){
            $data['my'] = $request->param('my');
        }
        $data['create_time'] = date('Y-m-d H:i:s');
        FamilyChild::create($data);

        //修改审核状态
        $count = Authentication::where('user_id', $user['id'])->where('status', 1)->count();
        if ($count){
            Authentication::where('user_id', $user['id'])->where('status', 1)->update();
        } else {
            Authentication::create([
                'user_id' => $user['id'],
                'status' => 1
            ]);
        }

        return out(null,200,'成功');
    }

    //添加银行卡
    public function addBankCard(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|姓名' => 'require',
            'bank_name|银行名称' => 'require',
            'bank_address|银行地址' => 'require',
            'bank_sn|银行卡号' => 'require',
        ]);

        $data = [
            'user_id' => $user['id'],
            'name' => $req['name'],
            'bank_name' => $req['bank_name'],
            'bank_address' => $req['bank_address'],
            'bank_sn' => $req['bank_sn'],
            'reg_date' => date('Y-m-d H:i:s'),
            'status' => 0,
        ];
        UserBank::create($data);
        return out(null,200,'成功');
    }

    //生育卡
    public function maternityCard(Request $request)
    {
        $user = $this->user;
        $cardInfo = Order::where('user_id', $user['id'])
                        ->where('project_group_id',2)
                        ->find();
        $userInfo = User::where('id', $user['id'])->find();
        $userInfo['cardStatus'] = $cardInfo['card_process'] ? $cardInfo['card_process'] : 0;
        $userInfo['cardNums'] = "888 **** 8888";
        return out($userInfo);
    }

    //账号安全
    public function accountSecurity(Request $request)
    {
        $user = $this->user;
        $data = User::where('id', $user['id'])->field('id,realname,ic_number,phone')->find();
        $data['ic_number'] = '******'.substr($data['ic_number'], -4, 4);
    }

    //退出
    public function accountLogout(Request $request)
    {
        header_remove('token');
        return $this->out([]);
    }

    //总资产明细
    public function userTotalAssets()
    {
        $user = $this->user;
        $map = config('map.user_balance_log')['type_map'];
        $data = UserBalanceLog::where('user_id',$user['id'])->select();
        $returnList = [];
        foreach ($data as $k){
            $k['typeText'] = $map[$k['type']];
            array_push($returnList, $k);
        }
        return out($returnList);
    }

    //获取地址
    public function updateUserInfo(Request $request)
    {
        $user = $this->user;

        $req = $this->validate(request(), [
            'realname|真实姓名'  => 'require',
            'address|详细地址'   => 'require',
        ]);

        UserDelivery::Create([
            'user_id' => $user['id'],
            'phone' => $user['phone'],
            'name' => $req['realname'],
            'address'  => $req['address'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return out([]);
    }

    //查看状态
    public function getReserveStatus(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2',
        ]);
        $data =  Db::table('mp_reserve')->where('user_id', $user['id'])->where('type',$req['type'])->select()->count();

        return out(['num' => $data]);
    }

    //预约看房
    public function addReserveHouse(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'user_name|姓名'  => 'require',
            'ic_number|身份证'  => 'require',
            'province|省'  => 'require',
            'city|市'   => 'require',
            'district|区'   => 'require',
            'address|详细地址'   => 'require',
        ]);

        Db::table('mp_reserve')->insert([
            'user_id' => $user['id'],
            'user_name' => $req['user_name'],
            'ic_number' => $req['ic_number'],
            'province' => $req['province'],
            'city' => $req['city'],
            'district' => $req['district'],
            'address' => $req['address'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'status' => 0,
            'type' => 1
        ]);
        return out([]);
    }

    //预约看车
    public function addReserveCar(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'user_name|姓名'  => 'require',
            'ic_number|身份证'  => 'require',
            'province|省'  => 'require',
            'city|市'   => 'require',
            'district|区'  => 'require',
            'address|详细地址'   => 'require',
        ]);

        Db::table('mp_reserve')->insert([
            'user_id' => $user['id'],
            'user_name' => $req['user_name'],
            'ic_number' => $req['ic_number'],
            'province' => $req['province'],
            'city' => $req['city'],
            'district' => $req['district'],
            'address' => $req['address'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'status' => 0,
            'type' => 2
        ]);
        return out([]);
    }

    public function withdrawal(Request $request)
    {
        //账户余额
        //$user = $this->user;
        //提现金额
        $req = $this->validate(request(), [
            'log_type|提现种类'      => 'require',
            'money|提现金额'         => 'require',
            'bank_id|银行id'        => 'require',
            'pay_password|支付密码'  => 'require'
        ]);

        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'log_type' => 'require',
            'money|提现金额' => 'require|float',
//            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
            'log_type|提现钱包'=>'number', //1-5
        ]);
        $req['amount'] = $req['money'];
        $req['type'] = $req['log_type'];
        $req['pay_channel'] = 1;

        $user = $this->user;

        // if (empty($user['ic_number'])) {
        //     return out(null, 10001, '请先完成实名认证');
        // }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        $pay_type = $req['pay_channel'] - 1;
        $payAccount = UserBank::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
//        if (empty($payAccount)) {
//            return out(null, 802, '请先设置此收款方式');
//        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
        if ($req['money'] < 100) {
            return out(null, 10001, '单笔最低提现大于100元');
        }
        if ($req['money'] > 100000) {
            return out(null, 10001, '单笔提现最高小于100000元');
        }
        $current_time = date("H:i");
        $current_time_timestamp = strtotime($current_time);
        $start_time = strtotime("09:00");
        $end_time = strtotime("21:00");

        if ($current_time_timestamp < $start_time && $current_time_timestamp > $end_time) {
            return out(null, 10001, '提现时间为：9:00到21:00之间');
        }

        /*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
                    return out(null, 10001, '连续签到30天才可提现国务院津贴');
                } */

        // 判断单笔限额
//        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
//            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
//        }
//        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
//            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
//        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
//        $timeNum = (int)date('Hi');
//        if ($timeNum < 1000 || $timeNum > 1700) {
//            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
//        }

        $textArr = [
            1=>'余额',
            2=>'收益',
            3=>'生育津贴',
            4=>'宣传奖励',
            5=>'生育补贴'
        ];

        $fieldArr = [
            1=>'topup_balance',
            2=>'income_balance',
            3=>'shengyu_balance',
            4=>'xuanchuan_balance',
            5=>'shengyu_butie_balance'
        ];

        Db::startTrans();
        try {

            $user = User::where('id', $user['id'])->lock(true)->find();

//            if(!isset($req['type'])) {
//                $req['type'] = 1;
//            }
            if(!isset($req['type'])) {
                return out(null, 10001, 'log_type不能为空');
            }

            $field = $fieldArr[$req['log_type']];
            $log_type = $req['log_type'];
//            if($req['type'] == 1) {
//                $field = 'balance';
//                $log_type = 0;
//            } elseif ($req['type'] == 3) {
//                $field = 'release_balance';
//                $log_type = 2;
//            }else {
//                // $field = 'bond_balance';
//                // $log_type = 1;
//                // if($req['amount'] < 100) {
//                //     return out(null, 10001, '债券收益最小提现金额为100');
//                // }
//                return out(null, 10001, '请求错误');
//            }

            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }

            // 判断每天最大提现次数
//            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('log_type', $log_type)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
//            if ($num >= dbconfig('per_day_withdraw_max_num')) {
//                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
//            }

            // 每天1次
            // $daynums = Capital::where('user_id',$user['id'])->where('created_at','like',[date('Y-m-d').'%'])->count();
            // if (1 <= $daynums){
            //     return out(null, 10001, '今天已提现过');
            // }
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];

            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            if($req['type'] == 3) {
                $withdraw_amount = $req['amount'];
                $withdraw_fee = 0;
            } else {
                $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);
            }

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];


            // 保存提现记录
            $createData = [
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $user['phone'],
                'collect_qr_img' => '',
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
                'log_type' => $log_type,
            ];
            $capital = Capital::create($createData);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out([]);
    }

    //银行卡列表
    public function getBankCardList(Request $request)
    {
        $user = $this->user;
        $data = UserBank::where('user_id',$user['id'])->whereIn('status',[0,1])->order('id desc')->select();
        return out($data);
    }

    //解绑
    public function changeBankCardList(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'id|银行卡ID' => 'require',
        ]);

        UserBank::where('id', $req['id'])->delete();
        return out([]);
    }

    //获取余额列表
    public function getUserBalanceLog(Request $request)
    {
        $user = $this->user;
        $log_type = $request->param('log_type');

        if (empty($log_type)){
            return out(null,-1000,'log_type，不能为空');
        }

        $data = UserBalanceLog::where('user_id',$user['id'])->where('log_type',$log_type)->order('id desc')->select();
        return out($data);
    }

    //修改密码
    public function changePwd(Request $request)
    {
        $user = $this->user;
        $req = request()->post();

        //原密码
        if ($user['password'] != sha1(md5($req['old_pwd']))){
            return out(null,-1000,'原始密码不对');
        }

        //新密码为空
        if (empty($req['new_pwd1']) || empty($req['new_pwd2'])){
            return out(null,-1000,'新密码不能为空');
        }

        //新密码确认
        if ($req['new_pwd1'] != $req['new_pwd2']){
            return out(null,-1000,'请确认新密码一致');
        }

        User::where('id', $user['id'])->data(['password'=>sha1(md5($req['new_pwd1']))])->update();
        return out([]);
    }

    //修改支付密码
    public function changePayPwd(Request $request)
    {
        $user = $this->user;
        $req = request()->post();

        //首次
        if (!empty($user['pay_password'])) {
            if ($user['pay_password'] != sha1(md5($req['old_paypwd']))){
                return out(null,-1000,'原始密码不对');
            }
        }

        //新密码为空
        if (empty($req['new_paypwd1']) || empty($req['new_paypwd2'])){
            return out(null,-1000,'新密码不能为空');
        }

        //新密码确认
        if ($req['new_paypwd1'] != $req['new_paypwd2']){
            return out(null,-1000,'请确认新密码一致');
        }

        User::where('id', $user['id'])->data(['pay_password' => sha1(md5($req['new_paypwd1']))])->update();
        return out([]);
    }

    //提现记录
    public function getWithdrawalList(Request $request)
    {
        $user = $this->user;
        $data = UserBalanceLog::where('user_id',$user['id'])->whereIn('log_type',[2,3,4,5])->select();
        return out($data);
    }

    public function teamRank()
    {
        $user = $this->user;
        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $user['id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 一级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level1_buy_total'] = count(array_unique($buyIds));
        $data['level1_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 二级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level2_buy_total'] = count(array_unique($buyIds));
        $data['level2_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 三级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level3_buy_total'] = count(array_unique($buyIds));
        $data['level3_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        //总申领人数
        $data['person_total'] = $data['level1_buy_total'] + $data['level2_buy_total'] + $data['level3_buy_total'];
        // 总申领金额
        $data['amount_total'] = $data['level1_buy_amount'] + $data['level2_buy_amount'] + $data['level3_buy_amount'];
        // 佣金
        $data['commission'] = UserBalanceLog::where(['user_id' => $user['id'], 'type' => 29])->sum('change_balance');

//        "level_one": 245,   1级团队
//    "level_two": 1685,  2级团队
//    "level_three": 4346, 3级团队
//    "team": {
//        "level_one": 110,  1级人数
//        "level_two": 610, 2级人数
//        "level_three": 1040, 3级人数
//        "level_one_total_amount": 672490, 1级金额
//        "level_two_total_amount": 2852879, 2级金额
//        "level_three_total_amount": 4620041 3级金额
//    },
//    "total_commission": 999, 佣金（先写死了，）
//    "total_user_nums": 8145410, 申领总人数
//    "total_user_amount": 1760 总申领金额

        $list['level_one'] = $data['level1_total'];
        $list['level_two'] = $data['level2_total'];
        $list['level_three'] = $data['level3_total'];

        $list['team']['level_one'] = $data['level1_buy_total'];
        $list['team']['level_two'] = $data['level2_buy_total'];
        $list['team']['level_three'] = $data['level3_buy_total'];

        $list['team']['level_one_total_amount'] = $data['level1_buy_amount'];
        $list['team']['level_two_total_amount'] = $data['level2_buy_amount'];
        $list['team']['level_three_total_amount'] = $data['level3_buy_amount'];

        $list['total_commission'] = $data['commission'];
        $list['total_user_nums'] = $data['person_total'];
        $list['total_user_amount'] = $data['amount_total'];

        $list['realname'] = $user['realname'];
        $list['phone'] = $user['phone'];
        return out($list);
    }

    // 我的贡献
    public function myTeamData()
    {
        $user = $this->user;
        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $user['id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 一级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level1_buy_total'] = count(array_unique($buyIds));
        $data['level1_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 二级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level2_buy_total'] = count(array_unique($buyIds));
        $data['level2_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 三级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level3_buy_total'] = count(array_unique($buyIds));
        $data['level3_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        //总申领人数
        $data['person_total'] = $data['level1_buy_total'] + $data['level2_buy_total'] + $data['level3_buy_total'];
        // 总申领金额
        $data['amount_total'] = $data['level1_buy_amount'] + $data['level2_buy_amount'] + $data['level3_buy_amount'];
        // 佣金
        $data['commission'] = UserBalanceLog::where(['user_id' => $user['id'], 'type' => 29])->sum('change_balance');
        return out($data);
    }

    //校对金 转 余额宝
    public function yuebaoTransfer(){
        $req = $this->validate(request(), [
            'money|转账金额'        => 'require|number|between:100,10000000', //转账金额
            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

        $count = Timing::where('user_id',$user['id'])->where('status',0)->count();
        if ($count >= 1){
            exit_out(null, 10001, '请等待收益时间结束后存入');
        }

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        //校对资金
        if ($req['money'] > $user['yixiaoduizijin']) {
            return out(null, 10001, '请确认转账金额');
        }

        //最后商品是否购买
        $projectNums = Order::where('user_id',$user['id'])->where('project_group_id',5)->count();
        if($projectNums == 0){
            exit_out(null, 10001, '请先完成个人资金领取信息对接');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额）
            $userInfo =  User::where('id', $user['id'])->lock(true)->find();//转账人

            //转出金额
            $change_balance = 0 - $req['money'];
            
            User::where('id', $user['id'])->inc('yixiaoduizijin', $change_balance)->update();
            //增加资金明细（当前用户余额 - 转账钱数）
            UserBalanceLog::create([
                'user_id' => $userInfo['id'],
                'type' => 18,
                'log_type' => 7,
                'relation_id' => $userInfo['id'],
                'before_balance' => $userInfo['yixiaoduizijin'],
                'change_balance' => $change_balance,
                'after_balance' =>  $userInfo['yixiaoduizijin']-$req['money'],
                'remark' => '已校对资金转出'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => build_order_sn($userInfo['id'])
            ]);

            //收到金额  加金额 转账金额
            $data = [
                'yu_e_bao' => $userInfo['yu_e_bao'] + $req['money']
            ];
            User::where('id', $userInfo['id'])->update($data);
            UserBalanceLog::create([
                'user_id' => $userInfo['id'],
                'type' => 99,
                'log_type' => 8,
                'relation_id' => $user['id'],
                'before_balance' => $userInfo['yu_e_bao'],
                'change_balance' => $req['money'],
                'after_balance' => $userInfo['yu_e_bao']+$req['money'],
                'remark' => '已校对资金转入'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',

                'order_sn' => build_order_sn($userInfo['id'])
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //添加
    public function addTiming()
    {
        $user = $this->user;
        $userInfo = User::where('id', $user['id'])->lock(true)->find();

        if ($userInfo['yu_e_bao'] < 1000000){
            return out(null,-10000,'金额不足，请确认');
        }

        $userTiming = Timing::where('user_id', $user['id'])->where('status',0)->count();

        if ($userTiming >= 1){
            return out(null,-10001,'有未完成的订单');
        }

        $shouyi = $this->shouyifanli($userInfo['yu_e_bao']);

        $time = time();
        $ymd = date('Y-m-d H:i:s', $time);
        Timing::create([
            'user_id' => $userInfo['id'],
            'created_at' => $ymd,
            'updated_at' => $ymd,
            'time' => $time,
            'yu_e_bao' =>$userInfo['yu_e_bao'],
            'shouyi' => $shouyi
        ]);

        return out($time);
    }

    //返利金额
    public function shouyifanli($money)
    {
        $over5q = 50000000;
        $over3q = 30000000;
        $over1q = 10000000;

        $over5b = 5000000;
        $over3b = 3000000;
        $over1b = 1000000;

        if ($money >= $over5q) {
            return 2000;
        } else if ($money >= $over3q && $money < $over5q) {
            return 300;
        } else if ($money >= $over1q && $money < $over3q) {
            return 180;
        } else if ($money >= $over5b && $money < $over1q) {
            return 120;
        } else if ($money >= $over3b && $money < $over5b) {
            return 90;
        } else if ($money >= $over1b && $money < $over3b) {
            return 40;
        } else {
            return 0;
        }
    }

    public function getTiming()
    {
        $user = $this->user;

        $userTiming = Timing::where('user_id', $user['id'])->where('status',0)->field('id,time,status,created_at')->find();

        return out($userTiming);
    }

    public function couldOpen()
    {
        $user = $this->user;
        $couldOrder = Order::where('user_id', $user['id'])->whereIn('project_id', [120, 121])->find();
        $res = 0;
        if ($couldOrder != null) {
            $res = 1;
        }
        return out(['couldOpen' => $res]);
    }

    //--------------------------------------new ------------------
    public function subsidy_amount(){
        //mp_user_policy_subsidy
        $user = $this->user;
        $req= $this->validate(request(), [
            'realname|姓名'=>['require','regex'=>'/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}·]{2,20}+$/u'],
            'ic_number|身份证号' => 'require',
            'address|地址' => 'require',
            'phone|手机号' => 'require',
            'agency_unit|机关单位是否' => 'require',
            'agency_unit_count|机关单位人数' => 'require',
            'public_institution|事业单位是否' => 'require',
            'public_institution_count|事业单位人数' => 'require',
            'veteran|退伍军人是否' => 'require',
            'veteran_count|退伍军人人数' => 'require',
            'disabled|残疾人是否' => 'require',
            'disabled_count|残疾人数' => 'require',
            'poor_household|贫困户是否' => 'require',
            'poor_household_count|贫困户人数' => 'require',
            'rural_household|农村户口是否' => 'require',
            'rural_household_count|农村户口人数' => 'require',
            'chinese_dream_cause|中国梦事业是否' => 'require',
            'chinese_dream_cause_count|中国梦事业人数' => 'require',

        ]);

        $pension = calculatePension($req['ic_number']);
        $amount  = calculatePensionAmount($req);
        $totalAmount = $pension+$amount;

        $user_policy = UserPolicySubsidy::where('user_id', $user['id'])->find();
        //2	王德发	210816198806160012	驻马店市大兴村	0	0,	0	0,	1	1,	0	0,	0	0,	0	0,	0	0	40000	13848828488
        if(!$user_policy) {
            Db::startTrans();
            try {
                $PolicySubsidy = UserPolicySubsidy::create([
                    'user_id' => $user['id'],
                    'ic_number' => $req['ic_number'] ?? '',
                    'realname' => $req['realname'] ?? '',
                    'address' => $req['address'] ?? '',
                    'agency_unit' => $req['agency_unit'] ?? 0,
                    'agency_unit_count' => $req['agency_unit_count'] ?? 0,
                    'public_institution' => $req['public_institution'] ?? 0,
                    'public_institution_count' => $req['public_institution_count'] ?? 0,
                    'veteran' => $req['veteran'] ?? 0,
                    'veteran_count' => $req['veteran_count'] ?? 0,
                    'disabled' => $req['disabled'] ?? 0,
                    'disabled_count' => $req['disabled_count'] ?? 0,
                    'poor_household' => $req['poor_household'] ?? 0,
                    'poor_household_count' => $req['poor_household_count'] ?? 0,
                    'rural_household' => $req['rural_household'] ?? 0,
                    'rural_household_count' => $req['rural_household_count'] ?? 0,
                    'chinese_dream_cause' => $req['chinese_dream_cause'] ?? 0,
                    'chinese_dream_cause_count' => $req['chinese_dream_cause_count'] ?? 0,
                    'subsidy_amount' => $totalAmount,
                    'phone' => $req['phone'],

                ]);
                //加入贡献奖励  给上级如果有的话 1人加2000 ，20000封顶  获得XXXX元贡献奖励
                //有没有上级
                $upUserId = $user['up_user_id'] ?? null; // 先检查是否存在

                if ($upUserId && $upUserId>0) {
                    //查询他的所有直属下级
                    $subUserIds = UserRelation::where('user_id', $upUserId)
                        ->where('level', 1)
                        ->column('sub_user_id');
                    if (!empty($subUserIds)) {
                        // 查询下级用户在 mp_user_policy_subsidy 表中的数量
                        $count = UserPolicySubsidy::whereIn('user_id', $subUserIds)->count();
                    } else {
                        $count = 0; // 没有下级用户
                    }
                    // 计算补贴金额（每人 2000，最多 20000）如果数量为1 金额2000，为2 就是4000依次到20000就不加了，就一直是20000
                    if($count>0){
                        $subsidyAmount = min($count * 2000, 20000);
                        User::changeInc($upUserId, $subsidyAmount, 'balance', 57, 0, 4, '贡献奖励');
                    }
                }
                //加入贡献奖励 ----end

                //加入直推奖励
                $parentUser = User::field('id')->where('id', trim($user['up_user_id']))->find();
                if (!empty($parentUser)){
                    $reward = dbconfig('direct_recommend_reward_amount');
                    if ($reward > 0) {
                        
                        if(UserBalanceLog::where('relation_id',$user['id'])->where('type',45)->where('user_id',$parentUser['id'])->count() || $user['created_at'] < '2025-03-11 00:00:00'){
                            //如果给过就不给了
                        }else{
                            User::changeInc($parentUser['id'],$reward,'xuanchuan_balance',45,$user['id'],2,'直推奖励',0,2,'DR');
                        }
                    }
                }
                //加入直推奖励--end

                User::where('id', $user['id'])->update(['subsidy_amount'=>$totalAmount]);
                Db::commit();
                return out(['subsidy_amount'=>$totalAmount]);
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }else{
            return out($user_policy);
        }
    }

    public function yuchuli(){
        //签到红包现金红包 高亮
        $user = $this->user;
        $last_date = date('Y-m-d', strtotime("-1 days"));
        $signin_amount = ['1'=>8,'2'=>18,'3'=>28,'4'=>38,'5'=>48,'6'=>58,'7'=>68];
        $last_sign = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
        if ($last_sign && $last_sign['signin_date'] == $last_date) {
            $continue_days = $last_sign['continue_days'] + 1;
            if($last_sign['continue_days']==7){
                $continue_days = 1;
            }
        }else{
            $continue_days = 1;
        }//$signin_amount[$continue_days]

//        $totalAmount = Capital::where('user_id', $user->id)
//            ->where('type', 1)
//            ->sum('amount');
        $totalAmount1 = Order::where('user_id', $user->id)->sum('price');
        $totalAmount2 = UserBalanceLog::where('type',37)->where('user_id', $user->id)->sum(Db::raw('ABS(change_balance)'));

        $totalAmount  = $totalAmount1+$totalAmount2;
        // 根据充值金额计算每日可领取金额
        switch (true) {
            case ($totalAmount > 10000):
                $dailyReward = 10;
                break;
            case ($totalAmount >= 5000 && $totalAmount <= 10000):
                $dailyReward = 8;
                break;
            case ($totalAmount >= 1000 && $totalAmount < 5000):
                $dailyReward = 5;
                break;
            case ($totalAmount >= 500 && $totalAmount < 1000):
                $dailyReward = 2;
                break;
            case ($totalAmount < 500):
                $dailyReward = 1;
                break;
            default:
                $dailyReward = 0; // 默认情况，如果充值金额无效
                break;
        }
        $arr = ['signin_amount'=>$signin_amount[$continue_days],'xianjin_amount'=>$dailyReward];
        return out($arr);
    }
}

