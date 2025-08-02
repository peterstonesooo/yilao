<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\AuthGroup;
use app\model\AuthGroupAccess;
use app\model\Meetings;
use app\model\Order;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\ProjectHuodong;
use app\model\Ranking;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

class RankingController extends AuthController
{
    public function list()
    {
        $data = Ranking::select();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function showRanking()
    {
        $req = request()->get();
        $data = [];
        if (!empty($req['id'])){
            $data = Ranking::where('id', $req['id'])->find();

        }
        $this->assign('data', $data);

        return $this->fetch();
    }



    public function delRanking()
    {
        $req = request()->post();
        $this->validate($req, [
            'id' => 'require|number'
        ]);

        Ranking::where('id', $req['id'])->delete();

        return out();
    }



    public function editRanking()
    {
        $req = request()->post();

        if (!empty($req['id'])) {
            Ranking::where('id', $req['id'])->update($req);
        } else {

            Ranking::insert([
                'rank' => $req['rank'] ?? 1,  // 设置默认封面图
                'user_name' => $req['user_name'] ?? '默认姓名',  // 设置默认标题
                'phone' => $req['who'] ?? '************',  // 设置默认参与者
                'direct_push_count' => $req['direct_push_count'] ?? 1,  // 设置默认类型，如果没有提供
                'type' => $req['type'] ?? 1,  // 设置默认链接

            ]);
        }
        return out();
    }

}
