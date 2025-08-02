<?php

namespace app\common\command;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Filesystem;

class ExportDataTask extends Command
{
    protected function configure()
    {
        $this->setName('export:data')->setDescription('处理导出任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $tasks = Db::name('export_task')->where('status', 0)->select();

        if (empty($tasks)) {
            $output->writeln("暂无待处理任务");
            return;
        }

        foreach ($tasks as $task) {
            $output->writeln("开始处理任务ID：" . $task['id']);
            Db::name('export_task')->where('id', $task['id'])->update([
                'status' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            try {
                $sql = $task['params_sql'];
                $data = Db::query($sql);

                if (empty($data)) {
                    throw new \Exception("查询结果为空");
                }

                $headers = array_keys($data[0]);

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // 设置表头
                foreach ($headers as $col => $name) {
                    $sheet->setCellValueByColumnAndRow($col + 1, 1, $name);
                }

                // 写入数据
                foreach ($data as $row => $item) {
                    foreach ($headers as $col => $name) {
                        $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $item[$name]);
                    }
                }

                // 保存文件
                $dir = root_path() . 'public/export/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $fileName = 'export_' . $task['id'] . '_' . date('YmdHis') . '.xlsx';
                $filePath = $dir . $fileName;

                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);

                Db::name('export_task')->where('id', $task['id'])->update([
                    'status' => 2,
                    'file_path' => '/export/' . $fileName,
                    'progress' => 100,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $output->writeln("任务ID " . $task['id'] . " 导出成功");

            } catch (\Exception $e) {
                Db::name('export_task')->where('id', $task['id'])->update([
                    'status' => 3,
                    'error_msg' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $output->writeln("任务ID " . $task['id'] . " 导出失败：" . $e->getMessage());
            }
        }
    }
}
