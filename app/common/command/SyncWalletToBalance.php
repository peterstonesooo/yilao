<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\db\exception\DbException;
use think\facade\Db;

class SyncWalletToBalance extends Command
{
    protected function configure()
    {
        //需要把聊天app的可用资产 同步到项目app的现金钱包的团队奖励里
        $this->setName('sync:wallet')
            ->setDescription('同步远程 wallet 表的数据到本地用户表');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);
            // 查询钱包数据，分批获取
            $walletData = Db::connect('demo')->table('wallet')
                ->alias('w')
                ->leftJoin('chat_user u','w.user_id = u.id')
                ->field('w.user_id, w.assets, u.phone')
                ->where('w.assets', '>', 0)
                ->select();


            // 开始处理每一批数据
            foreach ($walletData as $wallet) {

                //$phone = Db::connect('demo')->table('chat_user')->where('id', $wallet['user_id'])->value('phone');

                // if (wallet) {
                    // 查找本地用户
                    $localUser = Db::table('mp_user')->where('phone', $wallet['phone'])->find();

                    if ($localUser) {
                        // 检查资金日志是否已有记录，避免重复奖励
                        $existingLog = Db::table('mp_user_wallet_balance_log')
                            ->where('user_id', $localUser['id'])
                            ->where('type', 8) // 8 = 团队奖励
                            ->count();

                        if ($existingLog > 0) {
                            $output->writeln("用户 {$wallet['phone']} 已经领取过团队奖励，跳过...");
                            continue;
                        }

                        // 使用事务处理，确保数据一致性
                        Db::startTrans();
                        try {
                            // 增加团队奖励
                            Db::table('mp_user')->where('id', $localUser['id'])->inc('xuanchuan_balance', $wallet['assets'])->update();

                            // 记录资金日志
                            Db::table('mp_user_wallet_balance_log')->insert([
                                'user_id'        => $localUser['id'],
                                'type'           => 8, // 团队奖励
                                'log_type'       => 1, // 余额日志
                                'relation_id'    => 0,
                                'before_balance' => $localUser['xuanchuan_balance'],
                                'change_balance' => $wallet['assets'],
                                'after_balance'  => $localUser['xuanchuan_balance'] + $wallet['assets'],
                                'remark'         => '可用资产平移',
                                'status'         => 2,
                                'created_at'     => date('Y-m-d H:i:s'),
                                'updated_at'     => date('Y-m-d H:i:s'),
                            ]);

                            Db::commit(); // 提交事务
                            //$output->writeln("成功同步用户 {$phone}，增加 {$wallet['assets']} 余额");
                        } catch (\Exception $e) {
                            Db::rollback(); // 回滚事务
                           // $output->writeln("同步用户 {$phone} 失败：{$e->getMessage()}");
                        }
                    }
                //}
            }

    }
}