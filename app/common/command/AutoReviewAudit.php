<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class AutoReviewAudit extends Command
{
    protected function configure()
    {
        $this->setName('autoReviewAudit')->setDescription('自动审核超时的审核记录');
    }

    protected function execute(Input $input, Output $output)
    {
        $now = date('Y-m-d H:i:s');

        $audits = Db::name('audit_records')
            ->where('status', 1) // 审核中
            ->where('review_time','<',time())
            ->select();

        if (!$audits->count()) {
            $output->writeln("没有超时的审核记录，任务结束...");
            return;
        }

        Db::startTrans();

        try {
            foreach ($audits as $audit) {
                $status = 2; // 默认审核通过
                $rejection_reason = '';

                // 如果是家庭成员审核，年龄需要 3 岁以下或 60 岁以上
                if ($audit['audit_type'] == 2) {
                    $age = date('Y') - date('Y', strtotime($audit['birth_date']));
                    if ($age > 3 && $age < 60) {
                        $status = 3; // 审核不通过
                        $rejection_reason = '年龄不符合要求';
                    }
                }

                Db::name('audit_records')->where('id', $audit['id'])->update([
                    'status' => $status,
                    'rejection_reason' => $rejection_reason,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $output->writeln("审核ID: " . $audit['id'] . " 结果: " . ($status == 2 ? "通过" : "不通过（原因：$rejection_reason)"));
            }

            Db::commit();
            $output->writeln("自动审核成功，共处理 " . count($audits) . " 条审核记录");
        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln("审核失败：" . $e->getMessage());
        }
    }
}