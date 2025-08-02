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

class SendMonthYanglaoTwo extends Command
{
    protected function configure()
    {
        $this->setName('sendMonthlyYanglaoTwo')->setDescription('养老服务项目收益发放2');
    }

    protected function execute(Input $input, Output $output)
    {
        //7月19日以后流转的会员不发日收益，只在7月26日发一笔到期收益就可以
        $limit = 5000;
        $lastId = 0;
        $totalProcessed = 0;

        $now = time();
        $cutoffDate = strtotime(date('Y') . '-07-18 23:59:59');
        $settlementDate = strtotime(date('Y') . '-07-26 00:00:00');

        if ($now < $settlementDate) {
            $output->writeln("当前日期尚未到达 7 月 26 日，跳过处理。");
            return;
        }

        $output->writeln("开始统一发放养老服务项目累计收益...");

        while (true) {
            $orders = Order::where('project_group_id', 8)
                ->where('status', '<>', 4) // 非已完成
                ->where('created_at', '>', date('Y') . '-07-19 00:00:00')
                ->where('id', '>', $lastId)
                ->order('id', 'asc')
                ->limit($limit)
                ->select();

            if ($orders->isEmpty()) {
                break;
            }

            Db::startTrans();
            try {
                foreach ($orders as $order) {
                    $userId = $order['user_id'];
                    $orderId = $order['id'];
                    $updateData = [];
                    $maturityIncome = 0;

                    if ($order['liuzhuan'] == 0) {
                        $uuu = User::where('id', $userId)->find();
                        $price = $uuu['subsidy_amount'] + $uuu['balance'] + $uuu['income_balance'];
                    } else {
                        $price = $order['liuzhuan'];
                    }

                    $annualYield = $order['annual_yield'];
                    $endTimestamp = strtotime(date('Y') . '-07-26 00:00:00');

                    if ($now >= $endTimestamp && $order['status'] != 4) {
                        $maturityIncome = round($price * $annualYield / 100, 2);
                        User::changeInc($userId, $maturityIncome, 'income_balance', 39, $orderId, 1, "养老订单到期收益");
                        $updateData = [
                            'gain_bonus' => Db::raw("gain_bonus + {$maturityIncome}"),
                            'status' => 4,
                        ];
                    }

                    if (!empty($updateData)) {
                        Order::where('id', $orderId)->update($updateData);
                    }

                    $output->writeln("用户ID: {$userId} 流转资金{$price}" . ($maturityIncome > 0 ? ", 到期收益 {$maturityIncome} 元" : ''));

                    $totalProcessed++;
                    $lastId = $orderId;
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln("发放失败：" . $e->getMessage());
                return;
            }
        }

        $output->writeln("发放完成，共处理订单数量：{$totalProcessed}");
    }
}
