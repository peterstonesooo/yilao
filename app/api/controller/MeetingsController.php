<?php

namespace app\api\controller;

use app\model\Meetings;
use app\model\Yuanmeng;
use app\model\YuanmengUser;
use app\model\UserRelation;
use think\facade\Cache;
use app\model\User;
use app\model\RelationshipRewardLog;
use Exception;
use think\facade\Db;

class MeetingsController extends AuthController
{

    public function list()
    {
//        $req = $this->validate(request(), [
//            'meeting_type|会议类型' => 'require',
//        ]);
        $data = Meetings::order('start_time', 'desc')->select();

        return out($data);

    }

}