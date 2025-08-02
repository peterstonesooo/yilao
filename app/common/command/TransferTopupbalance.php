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

class TransferTopupbalance extends Command
{

    protected function configure()
    {
        $this->setName('transferRechargeToRed')
            ->setDescription('将所有用户的充值余额转移到红包余额');
    }
    //分页 + 修改数据 = 不安全分页
    protected function execute(Input $input, Output $output)
    {
        $limit = 5000;
        $page = 1;
        $totalProcessed = 0;

        while (true) {
            // 分页查询充值余额大于0的用户
            $users = User::where('topup_balance', '>', 0)
                ->limit($limit)
                ->page($page)
                ->select();

            if ($users->isEmpty()) {
                break;
            }

            Db::startTrans();
            try {
                foreach ($users as $user) {
                    $amount = $user['topup_balance'];
                    $userId = $user['id'];

                    if ($amount <= 0) {
                        continue;
                    }

                    // 扣除充值余额
                    User::changeInc($userId, -$amount, 'topup_balance', 100, 0, 1, '转移到红包余额');

                    // 增加红包余额
                    User::changeInc($userId, $amount, 'shenianhongbao', 101, 0, 1, '来自现金钱包转入');

                    $output->writeln("用户 {$userId} 转移了 {$amount} 到红包余额");
                    $totalProcessed++;
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln("第 {$page} 页处理失败：" . $e->getMessage());
                break;
            }

            $page++;
        }

        $output->writeln("全部处理完成，共计处理 {$totalProcessed} 个用户");
    }
}