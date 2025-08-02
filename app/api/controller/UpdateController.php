<?php

namespace app\api\controller;

use app\common\controller\BaseController;


class UpdateController extends BaseController
{
    public function update_zhenshi()
    {
        // Git 仓库路径（请修改为你的项目路径）
        $repoPath = '/www/wwwroot/ylyx/yilaoyixiao';  // 请替换为你的 Git 代码目录

        // 切换到 Git 目录并执行命令
        $commands = [
            "cd {$repoPath}",
            "git pull",
            "git merge origin/dev"
        ];

        // 执行命令并捕获输出
        $output = [];
        $resultCode = 0;
        exec(implode(' && ', $commands), $output, $resultCode);

        // 检查执行结果
        if ($resultCode === 0) {
            return json(['status' => 1, 'message' => '--zhengshi--Git updated successfully', 'output' => $output]);
        } else {
            return json(['status' => 0, 'message' => '--zhengshi--Git update failed', 'output' => $output]);
        }
    }
    public function update_ceshi()
    {
        // Git 仓库路径（请修改为你的项目路径）
        $repoPath = '/www/wwwroot/yilaoyixiao_git/yilaoyixiao';  // 请替换为你的 Git 代码目录

        // 切换到 Git 目录并执行命令
        $commands = [
            "cd {$repoPath}",
            "git pull",
//            "git merge origin/dev"
        ];

        // 执行命令并捕获输出
        $output = [];
        $resultCode = 0;
        exec(implode(' && ', $commands), $output, $resultCode);

        // 检查执行结果
        if ($resultCode === 0) {
            return json(['status' => 1, 'message' => '--ceshi--Git updated successfully', 'output' => $output]);
        } else {
            return json(['status' => 0, 'message' => '--ceshi--Git update failed', 'output' => $output]);
        }
    }
}

