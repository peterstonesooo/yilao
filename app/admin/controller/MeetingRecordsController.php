<?php

namespace app\admin\controller;


use app\model\MeetingRecords;
use app\model\Setting;
use app\model\User;

class MeetingRecordsController extends AuthController
{
    public function list()
    {
        $req = request()->param();

        $data = $this->logList($req);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    private function logList($req)
    {
        $builder = MeetingRecords::order('id', 'desc');

        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        // 按签到时间筛选
        if (!empty($req['start_time'])) {
            $builder->where('created_at', '>=', $req['start_time']);
        }
        if (!empty($req['end_time'])) {
            $builder->where('created_at', '<=', $req['end_time']);
        }
        $data = $builder->paginate(['query' => $req]);
        return $data;
    }

    public function settingList()
    {


        $builder = Setting::order('id', 'asc');

        $data = $builder->where('key','meeting_signin_amount')->select();

        $this->assign('data', $data);

        return $this->fetch();
    }
}
