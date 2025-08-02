<?php

namespace app\admin\controller;

use app\model\User;
use app\model\UserBalanceLog;
use think\facade\Db;

class UserBalanceLogController extends AuthController
{
    public function userBalanceLogList()
    {
        $req = request()->param();

        //$req['log_type'] = 1;
        $data = $this->logList($req);
        $typeMap = config('map.user_balance_log.balance_type_map');

        foreach($data as &$item){
            if( $item['wallet_type']==1 ){
                $name = '现金余额';
            }elseif( $item['wallet_type']==2 ){
                $name = '团队奖励';
            }else{
                $name = '';
            }
            if($item['type']==18){
                $takename = User::where('id',$item['relation_id'])->value('phone');
                $item['remark1'] = $name."转账给".$takename;
            }
            if($item['type']==19){
                $takename = User::where('id',$item['relation_id'])->value('phone');
                $item['remark1'] = "来自".$takename."的转账";
            }

            $item['type_text'] = $typeMap[$item['type']];
        }

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }
    public function userWalletBalanceLogList()
    {
        $data = Db::table('mp_user_wallet_balance_log')->paginate(15)->each(function($item) {
            $item['phone'] = User::where('id', $item['user_id'])->value('phone');
            return $item;
        });
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function userIntegralLogList()
    {
        $req = request()->param();

        $req['log_type'] = 2;
        $data = $this->logList($req);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    private function logList($req)
    {
        $builder = UserBalanceLog::alias('a')
            ->field('a.*, u.phone')->join('mp_user u', 'a.user_id = u.id')->order('a.id', 'desc');

        if (isset($req['user_balance_log_id']) && $req['user_balance_log_id'] !== '') {
            $builder->where('a.id', $req['user_balance_log_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('a.user_id', $user_ids);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('a.type', $req['type']);
        }
        if (isset($req['log_type']) && $req['log_type'] !== '') {
            $builder->where('a.log_type', $req['log_type']);

        }
        if (isset($req['relation_id']) && $req['relation_id'] !== '') {
            $builder->where('a.relation_id', $req['relation_id']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req]);

        return $data;
    }
}
