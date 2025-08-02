<?php

namespace app\common\command;

use app\model\User;
use app\model\Order;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;


class Qingling5 extends Command
{
    protected function configure()
    {
        $this->setName('qingling5')->setDescription('处理会议参会列表 时长累计超过30分钟 发放奖励');
    }
    protected function execute(Input $input, Output $output)
    {
        while (true) {
            // 查询未处理的上传记录
            $task = Db::name('uploaded_excel')
                ->where('status', 0)
                ->order('id', 'asc')
                ->find();

            if (!$task) {
                $output->writeln("暂无待处理任务");
                break;
            }

            $file = root_path() . 'export/' . $task['file_name'];
            $taskId = $task['id'];

            if (!file_exists($file)) {
                Db::name('uploaded_excel')->where('id', $taskId)->update([
                    'status' => 3,
                    'error_msg' => '文件不存在'
                ]);
                $output->writeln("文件不存在：{$file}");
                continue;
            }

            $output->writeln("开始读取文件数据...");

            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                $durationMap = [];
                //处理会议参会列表 时长累计超过30分钟 发放奖励
                if ($task['remark'] === '参会列表累计超过30分钟发放奖励') {
                    foreach ($rows as $key => $row) {
                        if ($key === 0) continue;

                        $nicknameCol = $row[1] ?? '';
                        preg_match('/1[3-9]\d{9}/', $nicknameCol, $matches);
                        if (!$matches) continue;

                        $phone = $matches[0];
                        $timeText = $row[4] ?? '';
                        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeText)) continue;

                        [$h, $m, $s] = array_map('intval', explode(':', $timeText));
                        $seconds = $h * 3600 + $m * 60 + $s;

                        $durationMap[$phone] = ($durationMap[$phone] ?? 0) + $seconds;
                    }

                    $output->writeln("共提取手机号数：" . count($durationMap));

                    $processed = 0;
                    Db::startTrans();
                    foreach ($durationMap as $phone => $seconds) {
                        if ($seconds < 1800) continue;

                        $user = Db::name('user')->where('phone', $phone)->find();
                        if (!$user) continue;

                        User::changeInc($user['id'], 5, 'xuanchuan_balance', 53, 0, 1, '线上会议参会奖励', 1);
                        $output->writeln("用户 {$user['id']} - {$phone} 发放 5元（{$seconds} 秒）");
                        $processed++;
                    }
                    Db::commit();

                    Db::name('uploaded_excel')->where('id', $taskId)->update([
                        'status' => 2,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $output->writeln("任务完成，共发放：{$processed} 人");
                } else {
                    // 可添加其他 remark 类型的处理逻辑
                    $output->writeln("暂不支持的任务类型：{$task['remark']}");
                    Db::name('uploaded_excel')->where('id', $taskId)->update([
                        'status' => 3,
                        'error_msg' => '不支持的任务类型',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }

            } catch (\Exception $e) {
                Db::rollback();
                Db::name('uploaded_excel')->where('id', $taskId)->update([
                    'status' => 3,
                    'error_msg' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $output->writeln("发放失败：{$e->getMessage()}");
            }
        }

        $output->writeln("所有任务处理完成！");
    }

/*
 *  盟主号	    昵称	                手机号	            观看开始时间	            时长	        观看退出时间	        观看终端
 *  26675799	张永芝 ，	        15804141383	        2025-06-03 18:39:35	    00:00:13	2025-06-03 18:39:48	    H5
    35769399	张俊美		                            2025-06-03 18:39:27	    00:01:33	2025-06-03 18:41:00	    H5
    35672703	李文斌189191577      189191577	        2025-06-03 18:39:30	    00:02:52	2025-06-03 18:42:22	    H5
 *读取文件，处理数据，可能会有相同的手机号，叠加时长 超过30分钟 的发放奖励
 * */

}