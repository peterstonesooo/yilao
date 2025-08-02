<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class AutoFixUserRelations extends Command
{
    protected function configure()
    {
        $this->setName('autoFixUserRelations')->setDescription('修复 关系表');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = 5000;
        $lastId = 0;
        $totalProcessed = 0;

        while (true) {
            // 一次取5000条数据，按 id 正序，id 要大于上次的最后一条
            $users = Db::name('user')
                ->where('up_user_id', '>', 0)
                ->where('type', 0)
                ->where('id', '>', $lastId)
                ->order('id', 'asc')
                ->limit($limit)
                ->select();

            if ($users->isEmpty()) {
                break;
            }

            foreach ($users as $user) {
                $b_id = $user['id'];
                $current_up_id = $user['up_user_id'];
                $level = 1;

                Db::startTrans();
                try {
                    // 当前用户整段处理逻辑
                    while ($current_up_id > 0) {
                        if ($current_up_id == $b_id) {
                            // 防止自己绑定自己，跳出
                            break;
                        }
                        $exist = Db::name('user_relation')->where([
                            'user_id' => $current_up_id,
                            'sub_user_id' => $b_id,
                        ])->find();

                        if (!$exist) {
                            $up_user = Db::name('user')->where('id', $current_up_id)->find();

                            if (!$up_user) {
                                break;
                            }

                            Db::name('user_relation')->insert([
                                'user_id'      => $current_up_id,
                                'sub_user_id'      => $b_id,
                                'level'     => $level,
                                'is_active' => $user['is_active'],
                            ]);

                            $current_up_id = $up_user['up_user_id'];
                            $level++;
                        } else {
                            // 如果已经存在关系，直接跳到上级继续处理
                            $current_up_id = Db::name('user')->where('id', $current_up_id)->value('up_user_id');
                            $level++;
                        }
                    }
                    // 更新为已处理
                    Db::name('user')->where('id', $b_id)->update(['type' => 1]);
                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollback();
                    $output->writeln("用户ID {$b_id} 处理失败：" . $e->getMessage());
                }

                // 更新统计
                $totalProcessed++;
                $lastId = $b_id; // 更新最后处理的 ID
            }

            $output->writeln("已处理至ID：{$lastId}，累计处理用户：{$totalProcessed}");
        }

        $output->writeln("处理完成，总共处理用户数：{$totalProcessed}");
    }
}