<?php

namespace app\model;

use think\Model;

class AdminOperationLog extends Model
{
    public static function add($userId,$username,$action,$description){

        AdminOperationLog::create([
            'user_id'    => $userId,                    // 操作用户ID
            'username'   => $username,                        // 操作用户名，可以根据实际情况修改
            'action'     => $action,                   // 操作行为 update,add,del
            'description'=> $description,  // 操作描述 '为用户 ' . $req['id'] . ' 增加了抽奖次数'
            'ip_address' => request()->ip(),               // 操作IP地址
            'created_at' => date('Y-m-d H:i:s'),            // 操作时间，当前时间
        ]);

        return '';
    }
}
