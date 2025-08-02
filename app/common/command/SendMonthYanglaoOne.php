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

class SendMonthYanglaoOne extends Command
{
    protected function configure()
    {
        $this->setName('sendMonthlyYanglaoOne')->setDescription('养老服务项目收益发放');
    }

    protected function execute(Input $input, Output $output)
    {
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
                ->where('created_at', '<', date('Y') . '-07-19 00:00:00')
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

                    if ($order['liuzhuan'] == 0) {
                        $uuu = User::where('id', $userId)->find();
                        $price = $uuu['subsidy_amount'] + $uuu['balance'] + $uuu['income_balance'];
                    } else {
                        $price = $order['liuzhuan'];
                    }

                    $dailyRate = $order['daily_rate'];
                    $annualYield = $order['annual_yield'];
                    $endTimestamp = strtotime(date('Y') . '-07-26 00:00:00');

                    // 已发放的日收益次数（通过日志判断）
                    $logsCount = Db::name('user_balance_log')
                        ->where('type', 39)
                        ->where('relation_id', $orderId)
                        ->count();

                    $daysToGive = min(20 - $logsCount, $order['days']);



                    $dailyIncome = round($price * $dailyRate / 100, 2);
                    $totalDaily = round($dailyIncome * $daysToGive, 2);

                    if ($daysToGive <= 0) {
                        $lastId = $orderId;

                    }else{
                        // 发日收益
                        User::changeInc($userId, $totalDaily, 'income_balance', 39, $orderId, 1, "养老订单日收益{$daysToGive}天");
                    }

                    $updateData = [
                        'gain_bonus' => Db::raw("gain_bonus + {$totalDaily}"),
                        'next_bonus_time' => time(),
                    ];

                    // 如果已到期，发放到期收益
                    if ($now >= $endTimestamp && $order['status'] != 4) {
                        $maturityIncome = round($price * $annualYield / 100, 2);
                        User::changeInc($userId, $maturityIncome, 'income_balance', 39, $orderId, 1, "养老订单到期收益");
                        $updateData['gain_bonus'] = Db::raw("gain_bonus + {$totalDaily} + {$maturityIncome}");
                        $updateData['status'] = 4;
                    }

                    Order::where('id', $orderId)->update($updateData);
                    $output->writeln("用户ID: {$userId} 流转资金{$price} 补发日收益 {$totalDaily} 元" . (isset($maturityIncome) ? ", 到期收益 {$maturityIncome} 元" : ''));

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