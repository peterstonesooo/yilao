<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class ReplaceIdCardFrontDomain extends Command
{
    protected function configure()
    {
        $this->setName('replaceIdCardFrontDomain')
            ->setDescription('将 mp_audit_records 表中 id_card_front 字段的旧域名替换为新域名');
    }

    protected function execute(Input $input, Output $output)
    {
//        $oldDomain = 'http://niuniun.ommkj.com/';
        $oldDomain = 'http://7niu.ybvgq.cn/';
        $newDomain = 'http://77niu.cerhc.cn/';

        $pageSize = 1000;
        $lastId = 0;
        $updateCount = 0;

        do {
            $records = Db::name('audit_records')
                ->where('id', '>', $lastId)
                ->order('id')
                ->limit($pageSize)
                ->select();

            if ($records->isEmpty()) {
                break;
            }

            foreach ($records as $record) {
                $lastId = $record['id'];
                $idCardFront = $record['id_card_front'];

                if (empty($idCardFront)) {
                    continue;
                }

                if (strpos($idCardFront, $oldDomain) === 0) {
                    $newUrl = str_replace($oldDomain, $newDomain, $idCardFront);
                    Db::name('meeting_records')->where('id', $record['id'])->update(['id_card_front' => $newUrl]);
                    $updateCount++;
                    $output->writeln("ID： {$record['id']}更新前：{$record['id_card_front']} 更新成功：{$newUrl}");
                }
            }
        } while (true);

        $output->writeln("处理完成，更新总数：{$updateCount}");
    }
}