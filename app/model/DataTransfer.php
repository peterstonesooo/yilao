<?php

namespace app\model;

use think\Model;
use think\Db;

class DataTransfer extends Model
{
    public function importData()
    {
        // 连接远程数据库
        $sourceDb = Db::connect([
            'type'     => 'mysql', // 远程数据库类型
            'hostname' => 'hk-cdb-qxfbsra9.sql.tencentcdb.com', // 远程数据库地址
            'database' => 'zumeng_prod', // 远程数据库名称
            'hostport' => '24480', // 远程数据库端口，默认是3306
            'username' => 'root', // 远程数据库用户名
            'password' => 'eFNUmhFzH8haNtMs', // 远程数据库密码
            'charset'  => 'utf8mb4',
            'prefix'   => ''
        ]);

        // 查询远程数据库中的数据
        $data = $sourceDb->table('远程表名')->where('created_at', '>=', date('Y-m-d 00:00:00'))->select();

        if (empty($data)) {
            return json(['status' => 0, 'msg' => '没有需要导入的数据']);
        }

        // 处理数据并插入本地数据库
        $insertData = [];
        foreach ($data as $item) {
            $insertData[] = [
                'user_name'         => $item['user_name'],
                'phone'             => $item['phone'],
                'direct_push_count' => $item['direct_push_count'],
                'type'              => $item['type'],
                'created_at'        => $item['created_at'],
            ];
        }

        // 插入数据到本地数据库
        $res = Db::table('mp_ranking')->insertAll($insertData);

        if ($res) {
            return json(['status' => 1, 'msg' => "成功导入 $res 条数据"]);
        } else {
            return json(['status' => 0, 'msg' => '导入失败']);
        }
    }
}
