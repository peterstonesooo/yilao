<?php

namespace app\api\controller;

use app\model\Capital;
use app\model\Order;
use app\model\RedEnvelope;
use app\model\RedEnvelopeUserLog;
use app\model\UserBalanceLog;
use think\facade\Cache;
use app\model\UserRelation;
use app\model\User;
use Exception;
use think\facade\Db;

class RedEnvelopeController extends AuthController
{
    public function redEnvelopeList()
    {
        $user = $this->user;
        $prizeList = RedEnvelope::order('id', 'asc')->select()->each(function($item) use($user) {
            $item['is_get'] = 0;
            if (RedEnvelopeUserLog::where('user_id', $user['id'])->where('red_envelope_id', $item['id'])->find()) {
                $item['is_get'] = 1;
            }
            return $item;
        });
        return out($prizeList);
    }

    /**
     * 领取红包
     */
    public function redEnvelope()
    {
        $user = $this->user;
        $clickRepeatName = 'redEnvelope-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'id' => 'number'
        ]);

        if (dbconfig('red_envelope_switch') == 0) {
            return out(null, 10001, '该功能暂未开放');
        }
        //1 2 5 8 10
        if (!in_array($req['id'], [1, 2, 3, 4, 5])) {
            return out(null, 10001, '参数错误');
        }


        $redEnvelope = RedEnvelope::find($req['id']);

        //chankan chongzhi jine
        //这里的逻辑充值改成消费，就是有交钱消费购买任何产品就可以升级领取红包金额
//        $totalAmount = Capital::where('user_id', $user->id)
//            ->where('type', 1)
//            ->sum('amount');

        $totalAmount1 = Order::where('user_id', $user->id)->sum('price');
        $totalAmount2 = UserBalanceLog::where('type',37)->where('user_id', $user->id)->sum(Db::raw('ABS(change_balance)'));

        $totalAmount  = $totalAmount1+$totalAmount2;
        // 根据充值金额计算每日可领取金额
        switch (true) {
            case ($totalAmount > 10000):
                $dailyReward = 5;
                break;
            case ($totalAmount >= 5000 && $totalAmount <= 10000):
                $dailyReward = 4;
                break;
            case ($totalAmount >= 1000 && $totalAmount < 5000):
                $dailyReward = 3;
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

        if ($dailyReward < $req['id']) {
            return out(null, 10001, '抱歉，您不可以领取该等级红包');
        }

        $isExists = RedEnvelopeUserLog::where('user_id', $user->id)->where('red_envelope_id', $redEnvelope['id'])->where('created_at', '>', date('Y-m-d H:i:s', strtotime(date('Y-m-d'))))->find();
        if ($isExists) {
            return out(null, 10001, '每天只能领取一次');
        }

        Db::startTrans();
        try {
            
            $userLogId = RedEnvelopeUserLog::insertGetId([
                'user_id' => $user->id,
                'red_envelope_id' => $redEnvelope['id'],
                'number' => $redEnvelope['number'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            //不需要待确认，直接金额到账
            //User::changeInc($user->id,$redEnvelope['number'],'topup_balance',35,$userLogId,1, '每日现金红包','',1,'RE');
            User::changeInc($user->id,$redEnvelope['number'],'shenianhongbao',35,$userLogId,1, '每日现金红包');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(null, 200, $redEnvelope['number'].'元现金红包领取成功');
    }

    /**
     * 领取记录
     */
    public function signinRecord()
    {
        $user = $this->user;

        $list = RedEnvelopeUserLog::alias('l')->leftJoin('mp_red_envelope e', 'e.id = l.red_envelope_id')->where('l.user_id', $user['id'])->order('l.id', 'desc')->select()->toArray();
        
        return out([
            'list' => $list
        ]);
    }

}