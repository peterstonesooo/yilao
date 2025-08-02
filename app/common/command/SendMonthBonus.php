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

class SendMonthBonus extends Command
{
    protected function configure()
    {
        $this->setName('sendMonthlyBonus')->setDescription('健康服务项目收益发放');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = 100; // 每批处理 100 条
        $lastId = 0;
        $totalProcessed = 0;

        $output->writeln("健康服务项目收益发放开始...");

        while (true) {
            $orders = Order::where('project_group_id', 7)
                ->where('gain_bonus', 0)
                ->where('next_bonus_time', '<', time())
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

                    // 发放收益
                    User::changeInc($userId, $order['price'], 'income_balance', 12, $orderId,1, "返还投资款");

                    // 标记订单已处理
                    Order::where('id', $orderId)->update(['gain_bonus' => $order['price']]);

                    $output->writeln("用户ID: {$userId} 返还收益: {$order['price']}");
                    $lastId = $orderId;
                    $totalProcessed++;
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln("发放失败，错误信息：" . $e->getMessage());
                return;
            }
        }

        $output->writeln("发放完成，共处理订单数量：{$totalProcessed}");
    }
}