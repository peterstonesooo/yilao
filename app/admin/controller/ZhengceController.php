<?php

namespace app\admin\controller;


use app\model\ProjectHuodong;
use app\model\User;
use app\model\UserPolicySubsidy;
use think\App;

class ZhengceController extends AuthController
{


    public function showZhengce()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = UserPolicySubsidy::where('user_id',$req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function editZhengce()
    {
        $req= $this->validate(request(), [
            'user_id|user_id' => 'require',
            'realname|姓名'=>['require','regex'=>'/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}·]{2,20}+$/u'],
            'ic_number|身份证号' => 'require',
            'address|地址' => 'require',
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
            'phone|手机号' => 'require',
        ]);

        $pension = calculatePension($req['ic_number']);
        $amount  = calculatePensionAmount($req);
        $totalAmount = $pension+$amount;

        UserPolicySubsidy::where('user_id',$req['user_id'])->update([
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
        User::where('id', $req['user_id'])->update(['subsidy_amount'=>$totalAmount]);
        return out();
    }

}
