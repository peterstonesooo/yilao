<?php

namespace app\common\command;

use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class SendTeamLeaderSalary extends Command
{
    protected function configure()
    {
        $this->setName('sendTeamLeaderSalary')->setDescription('每月1号发放团队长月薪工资');
    }

    protected function execute(Input $input, Output $output)
    {
        $day = date('d');
        if ($day != '1') {
            $output->writeln("今天不是发工资日（每月1号），任务结束。");
            return;
        }

        // 工资档位，按照人数从高到低匹配 队长直推激活人数-金额
        $teamSalaryMap = [
            2000 => 60000,
            1000 => 40000,
            500 => 20000,
            200 => 8000,
            80 => 3000,
            40 => 1500,
        ];

        // 上个月时间范围
        $lastMonthStart = date('2025-04-01 00:00:00');
        $lastMonthEnd = date('2025-04-30 23:59:59');

        $pageSize = 5000;
        $lastId = 0;
        $num = 0;

        do {
            $users = User::where('id', '>', $lastId)
                ->order('id', 'asc')
                ->limit($pageSize)
                ->select();

            if ($users->isEmpty()) {
                break;
            }

            Db::startTrans();
            try {
                foreach ($users as $user) {
                    $lastId = $user->id;

                    if (!empty($user->salary_paid_at) && date('Y-m', strtotime($user->salary_paid_at)) === date('Y-m')) {
                        $output->writeln("用户ID {$user->id} 本月已发放团队长月薪，跳过！");
                        continue;
                    }

                    // 查询队长直推激活人数
                    $directActiveCount = UserRelation::where('user_id', $user->id)
                        ->where('is_active', 1)
                        ->where('level', 1)
//                        ->where('created_at','>=',$lastMonthStart)
//                        ->where('created_at','<=',$lastMonthEnd)
                        ->count();

                    // 判断薪资等级
                    $amount = 0;
                    foreach ($teamSalaryMap as $people => $money) {
                        if ($directActiveCount >= $people) {
                            $amount = $money;
                            break;
                        }
                    }
                    if ($amount > 0) {
                        User::where('id', $user->id)->update(['salary_paid_at' => date('Y-m-d')]);
                        User::changeInc($user->id, $amount, 'team_bonus_balance', 48,  $user->id, 1, '团队长月薪工资');
                        $output->writeln("发放用户ID： {$user->id} 手机号：{$user->phone} 实名姓名：{$user->realname} 团队长工资： {$amount}，直推激活人数： {$directActiveCount}");
                        $num++;
                    }
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln("发放失败：" . $e->getMessage());
            }

        } while (true);

        $output->writeln("团队长工资发放任务执行完成，总共发放 {$num} 个用户。");
    }
}