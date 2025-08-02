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

class SendMonthYanglao extends Command
{
    protected function configure()
    {
        $this->setName('sendMonthlyYanglao')->setDescription('养老服务项目收益发放');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = 5000;
        $lastId = 0;
        $totalProcessed = 0;
        //7月18日以前流转的会员，也全部在7月26日统一发完20天的日收益及到期收益。
        $output->writeln("开始发放养老服务项目日收益...");

        while (true) {
            $orders = Order::where('project_group_id', 8)
                ->where('status', '<>',4) // 收益中
//                ->where('next_bonus_time', '<', time())
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
                    if($order['liuzhuan']==0){
                        $uuu = User::where('id',$userId)->find();
                        $price = $uuu['subsidy_amount']+$uuu['balance']+$uuu['income_balance'];
                    }else{
                        $price = $order['liuzhuan'];
                    }

                    $dailyRate = $order['daily_rate'];  // 比如 3.5%

                    $annualYield = $order['annual_yield'];
                    $createAt = strtotime($order['created_at']);
                    // 日收益计算
                    $dailyIncome = round($price * $dailyRate / 100, 2);
                    $now = time();

                    $logMsg = "日收益";

                    // 检查是否到期
                    // 2. 判断是否到期，发放到期收益
                    $endTimestamp = $createAt + ($order['days'] * 86400);
                    if ($now >= $endTimestamp && $order['status'] != 4) {
                        // 到期了，发“到期收益”
                        $incomeAmount = round($price * $annualYield / 100, 2);
                        $logMsg = "到期收益";
                        $updateData = [
                            'gain_bonus' => Db::raw("gain_bonus + {$incomeAmount}"),
                            'status' => 4, // 标记为已完成
                        ];
                    } else {
                        // 发日收益
                        $incomeAmount = $dailyIncome;
                        $updateData = [
                            'gain_bonus' => Db::raw("gain_bonus + {$incomeAmount}"),
                            'next_bonus_time' => $order['next_bonus_time'] + 86400,
                        ];
                    }

                    $todayLogs = Db::name('user_balance_log')
                        ->where('type', 39)
                        ->where('created_at', '>=', date('Y-m-d') . ' 00:00:00')
                        ->where('created_at', '<=', date('Y-m-d') . ' 23:59:59')
                        ->where('relation_id', $orderId)->find();
                    // 今日是否已发日收益
                    if ($todayLogs) {
                        $lastId = $orderId;
                        continue;
                    }else{
                        // 发放收益
                        User::changeInc($userId, $incomeAmount, 'income_balance', 39, $orderId, 1, "养老订单{$logMsg}");
                        // 更新订单记录
                        Order::where('id', $orderId)->update($updateData);
                        $output->writeln("用户ID: {$userId}流转资金{$price} 发放 {$logMsg}：{$incomeAmount} 元");
                        $totalProcessed++;
                    }
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
