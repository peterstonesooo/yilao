<?php

namespace app\admin\controller;

use app\model\HongbaoSigninPrize;
use app\model\HongbaoSigninPrizeLog;
use app\model\HongbaoUserSetting;
use app\model\TurntableSignPrize;
use app\model\UserSignin;
use app\model\User;
use app\model\UserSigninPrize;

class SigninController extends AuthController
{
    //签到奖励设置
    public function setting()
    {
        $this->assign('data', HongbaoSigninPrize::select()->toArray());
        return $this->fetch();  
    }

    public function signinPrizeAdd()
    {
        return $this->fetch();
    }

    //砸金蛋概率设置
    public function prizeSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = HongbaoSigninPrize::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    //砸金蛋概率设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|名称' => 'require',
            'v|几率' => 'require',
        ]);

        if ($img_url = upload_file('img_url', false)) {
            $req['img_url'] = $img_url;
        }
        HongbaoSigninPrize::where('id', $req['id'])->update($req);
        return out();
    }

    public function addConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|名称' => 'require',
            'v|几率' => 'require',
        ]);

        $req['img_url'] = upload_file('img_url');
        HongbaoSigninPrize::create($req);

        return out();
    }

    //签到记录
    public function SigninLog()
    {
        $req = request()->param();
        $builder = UserSignin::order('id', 'desc');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $user_id = User::where('phone', $req['phone'])->find();
            $builder->where('user_id', $user_id['id']);
        }
        if (isset($req['signin_day']) && $req['signin_day'] !== '') {
            $builder->where('signin_date', $req['signin_day']);
        }
        
        $list = $builder->paginate(['query' => $req]);
        $this->assign('count', $builder->count());
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        HongbaoSigninPrize::destroy($req['id']);
        return out();
    }

    public function SigninPrizeLog()
    {
        $req = request()->param();
        $builder = HongbaoSigninPrizeLog::alias('l'); //->field('l.*, p.name, u.phone');

//        if (isset($req['prize_id']) && $req['prize_id'] !== '') {
//            $builder->where('l.prize_id', $req['prize_id']);
//        }
//
//        if (isset($req['user_id']) && $req['user_id'] !== '') {
//            $builder->where('l.user_id', $req['user_id']);
//        }
//
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }

//        $builder = $builder->leftJoin('mp_turntable_sign_prize p', 'l.prize_id = p.id')->leftJoin('mp_user u', 'l.user_id = u.id')->order('l.id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $prize = TurntableSignPrize::select();
        $this->assign('count', $builder->count());
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }

    public function luckyUser()
    {
        $req = request()->param();

        $builder = HongbaoUserSetting::order(['id' => 'desc']);//->where('class',1)
        // if (isset($req['project_id']) && $req['project_id'] !== '') {
        //     $builder->where('id', $req['project_id']);
        // }
//        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
//            $builder->where('project_group_id', $req['project_group_id']);
//        }
//        if (isset($req['name']) && $req['name'] !== '') {
//            $builder->where('name', 'like', '%'.$req['name'].'%');
//        }
//        if (isset($req['status']) && $req['status'] !== '') {
//            $builder->where('status', $req['status']);
//        }
        // if (isset($req['is_recommend']) && $req['is_recommend'] !== '') {
        //     $builder->where('is_recommend', $req['is_recommend']);
        // }

        $data = $builder->paginate(['query' => $req]);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function luckyUserAdd()
    {
        $prizeList = HongbaoSigninPrize::order('id asc')->select();
        $this->assign('prizeList', $prizeList);
        return $this->fetch();
    }

    //修改、添加内定
    public function hongbaoUserAdd()
    {
        $req = $this->validate(request(), [
            'prize_id|奖品名' => 'number',
            'phone|奖品名' => 'number',
        ]);

        $user = User::where('phone',$req['phone'])->find();
        if (empty($user)){
            exit_out(null, 10090, '请输入正确的电话号码');
        }

        $prize = HongbaoSigninPrize::where('id',$req['prize_id'])->find();
        if (empty($prize)){
            exit_out(null, 10090, '没找到对应的奖品');
        }

        if (!empty($req['id'])) {
            $req['user_id'] = $user['id'];
            $req['phone'] = $user['phone'];
            $req['prize_id'] = $prize['id'];
            $req['prize_name'] = $user['name'];
            HongbaoUserSetting::where('id', $req['id'])->update($req);
        } else {
            HongbaoUserSetting::insert([
                'user_id' => $user['id'],
                'phone' => $user['phone'],
                'prize_name' => $prize['name'],
                'prize_id' => $prize['id'],
                'status' => 0,
            ]);
        }
        return out();
    }

    //编辑
    public function changeLuckyStatus()
    {
        $req = $this->validate(request(), [
            'id|id' => 'number'
        ]);

        $req['status'] = 1;
        HongbaoUserSetting::where('id',$req['id'])->update($req);
        return out();
    }

    //删除
    public function luckyDelete()
    {
        $req = $this->validate(request(), [
            'id|id' => 'number'
        ]);

        HongbaoUserSetting::where('id',$req['id'])->delete();
        return out();
    }

}
