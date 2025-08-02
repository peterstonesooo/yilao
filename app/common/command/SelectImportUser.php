<?php

namespace app\common\command;

use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;


class SelectImportUser extends Command
{
    protected function configure()
    {
        $this->setName('selectImportUser')->setDescription('导出最近五天都未登入的会员，按团队三级总人数排序下来，比如他的三级人数总共是2000人，人数最多就排第一个');
    }

    protected function execute(Input $input, Output $output)
    {

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
        $output->writeln("开始创建目录！");
        // 设置一个超时限制（如果数据量很大，防止脚本超时）
        set_time_limit(0);

        // 设置5天前的时间
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-5 days'));

        // 打开文件，准备写入（覆盖模式）
        $fp = fopen($filePath, 'w');
        if (!$fp) {
            die('❌ 无法打开文件，可能是没有写入权限');
        }

        // 查询符合条件的用户并处理
        Db::name('user')
            ->where('last_login_time', '<', $cutoffDate)
            ->field('id, realname, phone')
            ->chunk(5000, function ($users) use (&$fp) {
                // 存储每个批次的用户数据
                $batchData = [];

                foreach ($users as $user) {
                    $uid = $user['id'];

                    // 查团队 1~3 级人数
                    $teamCount = Db::name('user_relation')
                        ->where('user_id', $uid)
                        ->whereIn('level', [1, 2, 3])
                        ->count();

                    // 构造输出内容
                    $batchData[] = [
                        'realname' => $user['realname'],
                        'phone' => $user['phone'],
                        'team_count' => $teamCount,
                    ];
                    echo "处理中... 姓名：{$user['realname']}，手机号：{$user['phone']}，团队人数：{$teamCount}\n";
                }

                // 将当前批次的数据按团队人数降序排序
                usort($batchData, function ($a, $b) {
                    return $b['team_count'] <=> $a['team_count'];
                });

                // 写入排序后的批次数据到文件
                foreach ($batchData as $user) {
                    $line = "姓名：{$user['realname']}，手机号：{$user['phone']}，团队人数：{$user['team_count']}\n";
                    fwrite($fp, $line);
                }

                // 回收内存
                gc_collect_cycles();
            });

        // 关闭文件
        fclose($fp);

        echo "数据已成功写入到文件：{$filePath}\n";
    }
}