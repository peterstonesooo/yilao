<?php

namespace app\api\controller;

use app\model\JijinOrder;
use app\model\Order;
use app\model\PaymentConfig;
use app\model\PrivateTransferLog;
use app\model\Project;
use app\model\Taxoff;
use app\model\ProjectHuodong;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\YuanmengUser;

class ProjectController extends AuthController
{
    public function projectList()
    {

        $req = $this->validate(request(), [
            'project_group_id' => 'number'
        ]);

        $data = Project::where('status', 1)
                ->where('project_group_id',$req['project_group_id'] )
                ->order(['sort' => 'asc', 'id' => 'asc'])
                ->paginate();

        // 遍历分页数据，格式化你想处理的字段
        foreach ($data as &$item) {
            // 去除小数点后多余的0
            if (isset($item['daily_rate'])) {
                $item['daily_rate'] = rtrim(rtrim($item['daily_rate'], '0'), '.');
            }

            if (isset($item['annual_yield'])) {
                $item['annual_yield'] = rtrim(rtrim($item['annual_yield'], '0'), '.');
            }

            if (isset($item['price'])) {
                $item['price'] = rtrim(rtrim($item['price'], '0'), '.');
            }
        }
        return out($data);
    }

    public function projectsList()
    {
        $data = Project::where('status', 1)->where('class',2)->order(['sort' => 'asc', 'id' => 'desc'])->paginate();
        foreach($data as $item){
            $item['cover_img']=$item['cover_img'];
        }
        return out($data);
    }

    public function projectFind()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
        ]);
        $data = Project::where('id',$req['project_id'] )->find();
        return out($data);
    }




    public function zuidiShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,private_bank_balance,jijin_shenbao_amount,yuan_shenbao_amount,private_bank_open,all_digit_balance')->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        if($user['jijin_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        } else {
            $data['all_digit_balance'] = $user['all_digit_balance'];
        }
        
        if($user['yuan_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($data['all_digit_balance'], $user['yuan_shenbao_amount'], 2);
        }
        
        if($data['all_digit_balance'] < 100000) {
            $data['all_digit_balance'] = 0;
        }
        if($data['all_digit_balance'] > 0) {
            $panduan = bcadd($data['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
            if($panduan > 0 && $panduan < 1000000) {
                $data['jijin_shenbao_radio'] = 3;
                $data['all_digit_balance'] = 100000;
            } elseif($panduan >= 1000000 && $panduan < 5000000) {
                $data['jijin_shenbao_radio'] = 2;
                $data['all_digit_balance'] = 300000;
            } elseif($panduan >= 5000000 ) {
                $data['jijin_shenbao_radio'] = 1;
                $data['all_digit_balance'] = 800000;
            }
            $data['jijin_shenbao_amount'] = bcmul($data['all_digit_balance'], ($data['jijin_shenbao_radio'] / 100), 2);
            $data['last_jijin_shenbao_amount'] = $data['jijin_shenbao_amount'];
            $data['is_off'] = 0;
            $time = time();
            //if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.5, 2);
                    $data['is_off'] = 5;
                }
            //}
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.8, 2);
            //         $data['is_off'] = 8;
            //     }
            // }
        } else {
            $data['all_digit_balance'] = 0;
        }
        return out($data);
    }

    public function zuidiYuanShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_monthly_subsidy,used_digital_yuan_amount,yuan_shenbao_amount,private_bank_balance,private_bank_open,all_digit_balance')->find();
        $jijin = JijinOrder::where('user_id', $user['id'])->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['all_digit_balance'] > 0 && !$jijin && $user['yuan_shenbao_amount'] == 0) {
        //     $user['all'] = 0;
        // } else
        if ($user['all_digit_balance'] > 0 && $jijin) {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['used_digital_yuan_amount'] - $user['used_monthly_subsidy'] - $user['yuan_shenbao_amount'];
        } else {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['yuan_shenbao_amount'];
        }
        
        if($user['all'] <= 0) {
            $user['all'] = 0;
        }

        if($user['all'] < 100000) {
            $user['all'] = 0;
        }
        if($user['all'] > 0) {
            $panduan = bcadd($user['all'], $user['yuan_shenbao_amount'], 2);
            if($panduan > 0 && $panduan < 1000000) {
                $user['yuan_shenbao_radio'] = 3;
                $user['all'] = 100000;
            } elseif($panduan >= 1000000 && $panduan < 5000000) {
                $user['yuan_shenbao_radio'] = 2;
                $user['all'] = 300000;
            } elseif($panduan >= 5000000) {
                $user['yuan_shenbao_radio'] = 1;
                $user['all'] = 800000;
            }
            $user['yuan_shenbao_amount'] = bcmul($user['all'], ($user['yuan_shenbao_radio'] / 100), 2);
            $user['last_yuan_shenbao_amount'] = $user['yuan_shenbao_amount'];
            $user['is_off'] = 0;
            $time = time();
           // if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.5, 2);
                    $user['is_off'] = 5;
                }
         //   }
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.8, 2);
            //         $user['is_off'] = 8;
            //     }
            // }
        }
        return out($user);
    }

    public function yuanShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_monthly_subsidy,used_digital_yuan_amount,yuan_shenbao_amount,private_bank_balance,private_bank_open,all_digit_balance')->find();
        $jijin = JijinOrder::where('user_id', $user['id'])->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['all_digit_balance'] > 0 && !$jijin && $user['yuan_shenbao_amount'] == 0) {
        //     $user['all'] = 0;
        // } else
        if ($user['all_digit_balance'] > 0 && $jijin) {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['used_digital_yuan_amount'] - $user['used_monthly_subsidy'] - $user['yuan_shenbao_amount'];
        } else {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['yuan_shenbao_amount'];
        }
        
        if($user['all'] <= 0) {
            $user['all'] = 0;
        }

        $panduan = bcadd($user['all'], $user['yuan_shenbao_amount'], 2);
        if($panduan < 1000000) {
            $user['yuan_shenbao_radio'] = 3;
           
        } elseif($panduan >= 1000000 && $panduan < 5000000) {
            $user['yuan_shenbao_radio'] = 2;
           
        } else {
            $user['yuan_shenbao_radio'] = 1;
            
        }

        if($user['all'] > 0) {
            $user['yuan_shenbao_amount'] = bcmul($user['all'], ($user['yuan_shenbao_radio'] / 100), 2);
            $user['last_yuan_shenbao_amount'] = $user['yuan_shenbao_amount'];
            $user['is_off'] = 0;
            $time = time();
           // if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.5, 2);
                    $user['is_off'] = 5;
                }
         //   }
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.8, 2);
            //         $user['is_off'] = 8;
            //     }
            // }
        }
        return out($user);
    }

    public function jijinShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,private_bank_balance,jijin_shenbao_amount,yuan_shenbao_amount,private_bank_open,all_digit_balance')->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['yuan_shenbao_amount'] > 0 && $user['jijin_shenbao_amount'] <= 0) {
        //     $data['all_digit_balance'] = 0;
        // } else {
        //     $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        // }
        if($user['jijin_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        } else {
            $data['all_digit_balance'] = $user['all_digit_balance'];
        }
        
        if($user['yuan_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($data['all_digit_balance'], $user['yuan_shenbao_amount'], 2);
        }
        
        $panduan = bcadd($data['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        if($panduan < 1000000) {
            $data['jijin_shenbao_radio'] = 3;
        } elseif($panduan >= 1000000 && $panduan < 5000000) {
            $data['jijin_shenbao_radio'] = 2;
        } else {
            $data['jijin_shenbao_radio'] = 1;
        }

        // if($data['all_digit_balance'] < 1000000) {
        //     $data['jijin_shenbao_radio'] = 3;
        // } elseif($data['all_digit_balance'] >= 1000000 && $data['all_digit_balance'] < 5000000) {
        //     $data['jijin_shenbao_radio'] = 2;
        // } else {
        //     $data['jijin_shenbao_radio'] = 1;
        // }

        if($data['all_digit_balance'] > 0) {
            $data['jijin_shenbao_amount'] = bcmul($data['all_digit_balance'], ($data['jijin_shenbao_radio'] / 100), 2);
            $data['last_jijin_shenbao_amount'] = $data['jijin_shenbao_amount'];
            $data['is_off'] = 0;
            $time = time();
            //if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.5, 2);
                    $data['is_off'] = 5;
                }
            //}
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.8, 2);
            //         $data['is_off'] = 8;
            //     }
            // }
        } else {
            $data['all_digit_balance'] = 0;
        }
        return out($data);
    }

    public function getoffList()
    {
        $user = $this->user;
        $data = Taxoff::where('user_id', $user['id'])->select();
        
    }
    public function huodong()
    {
        $data = ProjectHuodong::select();
        return out($data);
    }
    


    
    public function projectGroupList()
    {
        $req = $this->validate(request(), [
            'project_group_id' => 'require|number'
        ]);
        $user = $this->user;

        $data = Project::where('project_group_id', $req['project_group_id'])->where('status', 1)->append(['total_amount', 'daily_bonus', 'passive_income', 'progress','day_amount'])->select()->toArray();
        $withdrawSum = \app\model\User::cardWithdrawSum($user['id']);
        $recommendId = \app\model\User::cardRecommend($withdrawSum);

        foreach($data as &$item){
            //$item['intro']="";
            $item['card_recommend']=0;
            $item['cover_img']=get_img_api($item['cover_img']);
            $item['details_img']=get_img_api($item['details_img']);
            if($item['project_group_id']==5){
                if($recommendId == $item['id']){
                    $item['card_recommend']=1;
                }
            }
        }
        if($req['project_group_id']==5){
            array_multisort(array_column($data, 'card_recommend'), SORT_DESC, $data);
        }

        return out($data);
    }

    public function groupName(){
        $data = config('map.project.groupName');

        return out($data);
    }


    
        public function PaymentType(){
//        array(
//            1 => '微信',
//            2 => '支付宝',
//            3 => '线上银联',
//            4 => '线下银联',
//        ),
        $wechat_status = PaymentConfig::where('type', 1)->where('status', 1)->find();
        if($wechat_status){
            $wechat = 1;
        }else{
            $wechat = 0;
        }
        $alipay_status = PaymentConfig::where('type', 2)->where('status', 1)->find();
        if($alipay_status){
            $alipay = 1;
        }else{
            $alipay = 0;
        }
        $yinlian_status = PaymentConfig::where('type', 3)->where('status', 1)->find();
        if($yinlian_status){
            $yinlian = 1;
        }else{
            $yinlian = 0;
        }
        $yinlian2_status = PaymentConfig::where('type', 4)->where('status', 1)->find();
        if($yinlian2_status){
            $yinlian2 = 1;
        }else{
            $yinlian2 = 0;
        }
        $yunshan_status = PaymentConfig::where('type', 5)->where('status', 1)->find();
        if($yunshan_status){
            $yunshan = 1;
        }else{
            $yunshan = 0;
        }
        $data = array(['name'=>'微信','id'=>1,'status'=>$wechat],
            ['name'=>'支付宝','id'=>2,'status'=>$alipay],
            ['name'=>'线上银联','id'=>3,'status'=>$yinlian],
            ['name'=>'银行卡','id'=>4,'status'=>$yinlian2],
            ['name'=>'云闪付','id'=>5,'status'=>$yunshan]);

        return out($data);
    }
}
