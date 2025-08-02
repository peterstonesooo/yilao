<?php

namespace app\admin\controller;

use app\model\Hongli;
use app\model\HongliOrder;
use app\model\User;
use app\model\UserRelation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;


class HongliController extends AuthController
{
    //三农红利项目列表
    public function setting()
    {
        $this->assign('data', Hongli::order('sort', 'asc')->select()->toArray());
        return $this->fetch();  
    }

    //三农红利申请记录
    public function order()
    {
        $req = request()->param();
        $builder = HongliOrder::alias('l')->field('l.*, h.name, u.phone, u.realname,y.user_address');

        if (isset($req['hongli_id']) && $req['hongli_id'] !== '') {
            $builder->where('l.hongli_id', $req['hongli_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('l.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('l.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        
        $builder = $builder->leftJoin('mp_hongli h', 'l.hongli_id = h.id')->leftJoin('mp_user u', 'l.user_id = u.id')->leftJoin('mp_yuanmeng_user y', 'l.user_id = y.user_id')->order('l.id', 'desc');

        if (!empty($req['export'])) {
            
            $list = $builder->select();
            
            // foreach ($list as $v) {
            //     $v->address=$v['yuanmeng']['user_address'] ?? '';
            // }
            //echo 1;exit;
            create_excel($list, [
                'id' => '序号',
                'phone' => '用户',
                'realname'=>'姓名',  
                'price' => '领取金',
                'name' => '项目名称',
                'user_address' => '地址',
                'created_at' => '创建时间'
            ], '三农红利记录-' . date('YmdHis'));
        }
        $list = $builder->paginate(['query' => $req]);
        $prize = Hongli::select();
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }

    //项目设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|红利项目名' => 'require',
            'price|领取金' => 'require|number',
            'sort|排序号' => 'number',
        ]);

        if ($cover_img = upload_file('cover_img', false,false)) {
            $req['cover_img'] = $cover_img;
        }

        if (!empty($req['id'])) {
            Hongli::where('id', $req['id'])->update($req);
        } else {
            Hongli::insert([
                'name' => $req['name'],
                'sort' => $req['sort'],
                'price' => $req['price'],
                'cover_img' => $req['cover_img'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return out();
    }

    public function hongliAdd()
    {
        $req = request();
        if (!empty($req['id'])) {
            $data = Hongli::where('id', $req['id'])->find();
            $this->assign('data', $data);
        }
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        Hongli::destroy($req['id']);
        return out();
    }

    public function changeAdminUser()
    {
        $req = request()->post();

        Hongli::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }
    //8号直推20人以上的名单  排序过
    public function zhitui20()
    {
        // 设置日期范围
        $startDate = '2025-03-10 00:00:00';
        $endDate = '2025-03-10 23:59:59';
        // 1. 获取下级人数最多的 10 名用户
        $topUsers = UserRelation::where('level', 1) // 仅统计直接下级
        ->whereBetween('created_at', [$startDate, $endDate])
            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
            ->group('user_id')
            ->having('direct_push_count', '>', 20) // 直推人数大于 20
            ->order('direct_push_count DESC')
            ->limit(20) // 仅取前 20 条
            ->select()
            ->toArray();

        // 获取这些用户的 user_id
        $userIds = array_column($topUsers, 'user_id');
        if (!empty($userIds)) {
            // **批量禁用这些用户**
            User::whereIn('id', $userIds)->update(['status' => 0]);
            echo "以下账号已被禁用：" . implode(', ', $userIds). '<br>';
        } else {
            echo "没有符合条件的账号。<br>";
        }
        // 2. 从 User 表中获取用户名和手机号
        if (!empty($userIds)) {
            $userDetails = User::whereIn('id', $userIds)->column('id, realname, phone', 'id');
            // 合并用户信息
            foreach ($topUsers as &$user) {
                $user['user_name'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['realname'] : '未知';
                $user['phone'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['phone'] : '未知';
            }
        }
        // 5. 按照 `direct_push_count` 重新排序
        usort($topUsers, function ($a, $b) {
            return $b['direct_push_count'] - $a['direct_push_count'];
        });

        // 遍历处理手机号
        foreach ($topUsers as $k=>$user) {
            echo ($k+1).'姓名：'.$user['user_name'].'-电话：'.$user['phone'].'-人数：'.$user['direct_push_count']. '<br>';
        }
        die;
    }

    public function zhitui20Exel()
    {
        // 设置日期范围
        $startDate = '2025-03-10 00:00:00';
        $endDate = '2025-03-10 23:59:59';

        // 1. 获取直推人数超过 20 的前 20 名用户
        $topUsers = UserRelation::where('level', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
            ->group('user_id')
            ->having('direct_push_count', '>', 20)
            ->order('direct_push_count DESC')
            ->limit(20) // 限制前 20 名
            ->select()
            ->toArray();

        // 2. 获取这些用户的基本信息（姓名、手机号）
        $userIds = array_column($topUsers, 'user_id');
        if (!empty($userIds)) {
            $userDetails = User::whereIn('id', $userIds)->column('id, realname, phone', 'id');
            foreach ($topUsers as &$user) {
                $user['user_name'] = $userDetails[$user['user_id']]['realname'] ?? '未知';
                $user['phone'] = $userDetails[$user['user_id']]['phone'] ?? '未知';
            }
        }

        // 3. 递归查找上级（最多找 5 层）
        function findAllSuperiors($userId, $depth = 5)
        {
            $result = [];
            $currentId = $userId;
            for ($i = 1; $i <= $depth; $i++) {
                $user = User::where('id', $currentId)->field('up_user_id, phone, realname')->find();
                if (!$user || $user['up_user_id'] == 0) {
                    break; // 找到最上级
                }
                $directPushCount = UserRelation::where('user_id', $user['up_user_id'])->count(); // 计算直推人数
                $result[] = [
                    'phone' => $user['phone'],
                    'realname' => $user['realname'],
                    'direct_push_count' => $directPushCount
                ];
                $currentId = $user['up_user_id'];
            }
            return $result;
        }

        // 4. 生成 Excel 数据
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 表头
        $sheet->setCellValue('A1', '排名')
            ->setCellValue('B1', '手机号')
            ->setCellValue('C1', '姓名')
            ->setCellValue('D1', '直推人数');

        // 添加 5 层上级列
        for ($i = 1; $i <= 5; $i++) {
            $colBase = chr(69 + ($i - 1) * 3); // 计算列（E, H, K...）
            $sheet->setCellValue("{$colBase}1", "上级{$i}手机号")
                ->setCellValue(chr(ord($colBase) + 1) . "1", "上级{$i}姓名")
                ->setCellValue(chr(ord($colBase) + 2) . "1", "上级{$i}直推人数");
        }

        // 填充数据
        $row = 2;
        foreach ($topUsers as $index => $user) {
            $sheet->setCellValue("A$row", $index + 1)
                ->setCellValue("B$row", $user['phone'])
                ->setCellValue("C$row", $user['user_name'])
                ->setCellValue("D$row", $user['direct_push_count']);

            // 查找上级信息
            $superiors = findAllSuperiors($user['user_id']);
            for ($i = 0; $i < count($superiors) && $i < 5; $i++) {
                $colBase = chr(69 + $i * 3); // 计算列
                $sheet->setCellValue("{$colBase}{$row}", $superiors[$i]['phone'])
                    ->setCellValue(chr(ord($colBase) + 1) . "{$row}", $superiors[$i]['realname'])
                    ->setCellValue(chr(ord($colBase) + 2) . "{$row}", $superiors[$i]['direct_push_count']);
            }
            $row++;
        }

        // 5. 生成 Excel 文件
        $writer = new Xlsx($spreadsheet);
        $filePath = 'top_users_with_superiors.xlsx';
        $writer->save($filePath);

        echo "Excel 文件已生成：<a href='$filePath'>点击下载</a>";
    }
    //查询 1~3 级下级人数大于 100 的用户，并返回姓名、手机号、下级人数
    public function getTopUsers()
    {
        // 时间范围
//        $startDate = '2025-04-04 00:00:00';
//        $endDate = '2025-04-08 23:59:59';

        // 查询4月4日~8日新增的1~3级下级，按用户聚合，筛选新增数量 > 50 的用户
        $users = Db::table('mp_user_relation')
            ->alias('r')
            ->join('mp_user u', 'r.user_id = u.id')
            ->where('r.level', 'in', [1, 2, 3])
//            ->whereBetween('r.created_at', [$startDate, $endDate]) // 筛选新增时间
            ->group('r.user_id')
//            ->having('COUNT(r.sub_user_id) > 100')
            ->having('new_sub_count >= 50 AND new_sub_count <= 99')
            ->field('u.realname, u.phone, COUNT(r.sub_user_id) as new_sub_count')
            ->select();

        if (empty($users)) {
            echo "没有符合条件的用户\n";
            return;
        }


        foreach ($users as $user) {
            echo "姓名: {$user['realname']} - 手机号: {$user['phone']} - 新增人数: {$user['new_sub_count']}\n". '<br>';
        }
    }

    //三级总激活人数40人以上的数据
    public function getTopUsers111()
    {
        $users = Db::table('mp_user_relation')
            ->alias('r')
            ->join('mp_user sub', 'r.sub_user_id = sub.id') // 加入 sub_user，统计是否激活
            ->join('mp_user u', 'r.user_id = u.id') // u 是上级（团队长）
            ->where('r.level', 'in', [1, 2, 3])
            ->where('sub.is_active', 1) // 只统计已激活的下级
            ->group('r.user_id')
            ->having('active_sub_count >= 40')
            ->field('u.realname, u.phone, COUNT(r.sub_user_id) as active_sub_count')
            ->select();

        if (empty($users)) {
            echo "没有符合条件的用户\n";
            return;
        }

        foreach ($users as $user) {
            echo "姓名: {$user['realname']} - 手机号: {$user['phone']} - 激活人数: {$user['active_sub_count']}\n" . '<br>';
        }
    }

    //开户激活
    public function activateUsersFromAuditRecords()
    {
        // 获取所有 audit_type = '5' 的审核记录（假设审核通过）
        $auditUserIds = Db::name('audit_records')
            ->where('audit_type', '5')
            ->where('status', '2') // 可选：只激活审核通过的用户
            ->distinct(true)
            ->column('user_id');

        if (empty($auditUserIds)) {
            return json(['code' => 0, 'msg' => '没有符合条件的用户']);
        }

        // 查询这些用户中，尚未激活的用户
        $inactiveUsers = Db::name('user')
            ->whereIn('id', $auditUserIds)
            ->where('is_active', 0)
            ->select();

        if (empty($inactiveUsers)) {
            return json(['code' => 0, 'msg' => '所有用户都已激活']);
        }

        foreach ($inactiveUsers as $k=>$v){
            if($v['is_active']==0){
                User::where('id', $v['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $v['id'])->update(['is_active' => 1]);
                echo "ok-";
            }
        }
        // 执行激活逻辑
        //        $now = time();
        //        $userIdsToActivate = array_column($inactiveUsers, 'id');

        //        Db::name('mp_user')
        //            ->whereIn('id', $userIdsToActivate)
        //            ->update([
        //                'is_active'   => 1,
        //                'active_time' => $now
        //            ]);

        return json([
            'code' => 1,
            'msg'  => '激活成功',
        ]);
    }
    //导出最近五天都未登入的会员，按团队三级总人数排序下来，比如他的三级人数总共是2000人，人数最多就排第一个
    //select count(*) from mp_user where last_login_time<"2025-04-21 00:00:00";
    public function getInactiveTopUsers()
    {

//        $cutoffDate = date('Y-m-d H:i:s', strtotime('-5 days'));
//        $inactiveUserIds = [];
//
//        Db::name('user')
//            ->where('last_login_time', '<', $cutoffDate)
//            ->field('id')
//            ->chunk(10000, function ($users) use (&$inactiveUserIds) {
//                foreach ($users as $user) {
//                    $inactiveUserIds[] = $user['id'];
//                }
//            });
//
//        echo "共找到未登录用户：" . count($inactiveUserIds) . " 个\n";die;


        // 设置文件路径
        $filePath = __DIR__ . '/inactive_users.txt';

        // 检查文件保存的目录是否存在，若不存在则创建
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0777, true)) {
                die('❌ 无法创建目录，请检查权限');
            }
        }

        // 检查目录是否可写
        if (!is_writable($dirPath)) {
            die('❌ 目录不可写，请检查权限');
        }

        // 设置一个超时限制（如果数据量很大，防止脚本超时）
        set_time_limit(0);

        // 设置5天前的时间
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-5 days'));
        $allData = []; // 新建一个收集的数组

        // 打开文件写入模式
        $fp = fopen($filePath, 'a+');
        if (!$fp) {
            die('❌ 无法打开文件，可能是没有写入权限');
        }

        Db::name('user')->alias('u')
            ->where('u.last_login_time', '<', $cutoffDate)
            ->field('u.id, u.realname, u.phone')
            ->chunk(10000, function ($users) use ($fp) {
                foreach ($users as $user) {
                    $uid = $user['id'];

                    // 查团队 1~3 级人数
                    $teamCount = Db::name('user_relation')
                        ->where('user_id', $uid)
                        ->whereIn('level', [1, 2, 3])
                        ->count();

                    // 收集数据
                    $userData = [
                        'realname' => $user['realname'],
                        'phone' => $user['phone'],
                        'team_count' => $teamCount,
                    ];

                    // 将数据写入到文件
                    $line = "姓名：{$userData['realname']}，手机号：{$userData['phone']}，团队人数：{$userData['team_count']}\n";
                    fwrite($fp, $line);
                }

                // 回收内存
                gc_collect_cycles();
            });

        // 关闭文件
        fclose($fp);

        // 成功提示
        echo "数据已成功写入到文件：{$filePath}\n";
        // 最近5天未登录的截止时间点
//        $cutoffTime = date('Y-m-d H:i:s', strtotime('-5 days'));
//        Db::name('user')
//            ->where('last_login_time', '<', $cutoffTime)
//            ->chunk(10000, function ($users) {
//                $ids = array_column($users->toArray(), 'id');
//
//                $results = Db::name('user_relation')
//                    ->alias('r')
//                    ->join('mp_user u', 'r.user_id = u.id')
//                    ->whereIn('r.user_id', $ids)
//                    ->whereIn('r.level', [1, 2, 3])
//                    ->group('r.user_id')
//                    ->field('u.realname, u.phone, COUNT(r.sub_user_id) as sub_count')
//                    ->order('sub_count', 'desc')
//                    ->select();
//
//                // 输出或保存
//                foreach ($results as $row) {
//                    echo "姓名: {$row['realname']} - 手机: {$row['phone']} - 三级人数: {$row['sub_count']}\n". '<br>';
//                }
//            });
    }
        //直推激活40人以上的数据
    public function getTopUsers222()
    {
        $users = Db::table('mp_user_relation')
            ->alias('r')
            ->join('mp_user sub', 'r.sub_user_id = sub.id') // 加入 sub_user，统计是否激活
            ->join('mp_user u', 'r.user_id = u.id') // u 是上级（团队长）
            ->where('r.level', 1)
            ->where('sub.is_active', 1) // 只统计已激活的下级
            ->group('r.user_id')
            ->having('active_sub_count >= 40')
            ->field('u.realname, u.phone, COUNT(r.sub_user_id) as active_sub_count')
            ->select();

        if (empty($users)) {
            echo "没有符合条件的用户\n";
            return;
        }

        foreach ($users as $user) {
            echo "姓名: {$user['realname']} - 手机号: {$user['phone']} - 激活人数: {$user['active_sub_count']}\n" . '<br>';
        }
    }


    public function getTopUsers123()
    {
        // 查询 会员三级总人数5人到19人的数据
        $users = Db::table('mp_user_relation')
            ->alias('r')
            ->join('mp_user u', 'r.user_id = u.id')
            ->where('r.level', 'in', [1, 2, 3])

            ->group('r.user_id')

            ->having('new_sub_count >= 5 AND new_sub_count <= 19')
            ->field('u.realname, u.phone, COUNT(r.sub_user_id) as new_sub_count')
            ->select();

        if (empty($users)) {
            echo "没有符合条件的用户\n";
            return;
        }

        foreach ($users as $user) {
            echo "姓名: {$user['realname']} - 手机号: {$user['phone']} - 新增人数: {$user['new_sub_count']}\n". '<br>';
        }
    }

}
