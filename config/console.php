<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'curd'	=>	'app\common\command\Curd',
        'rule'  =>  'app\common\command\Rule',
        'checkRelease'  =>  'app\common\command\CheckRelease',
        'activeRank'  =>  'app\common\command\ActiveRank',
        'checkSubsidy'  =>  'app\common\command\CheckSubsidy',
        'checkTeam'  =>  'app\common\command\CheckTeam',
        'checkTeamLeader'  =>  'app\common\command\CheckTeamLeader',
        'sendCashReward'  =>  'app\common\command\SendCashReward',
        'autoWithdrawAudit'  =>  'app\common\command\AutoWithdrawAudit',
        'genarateEthAdress' =>  'app\common\command\GenarateEthAdress',
        'checkAssetBonus'  =>  'app\common\command\CheckAssetBonus',
        'butie' => 'app\common\command\Butie',
        'makeKline' => 'app\common\command\MakeKline',
        'YuanMengChangeStatus' => 'app\common\command\YuanMengChangeStatus',
        'SignRewardAppend' => 'app\common\command\SignRewardAppend',
        'task'  =>  'app\common\command\Task',
        'tasks'  =>  'app\common\command\Tasks',
        'taskss'  =>  'app\common\command\Taskss',
        'tasksss'  =>  'app\common\command\Tasksss',
        'fix'  =>  'app\common\command\Fix',

        'checkBonus'  =>  'app\common\command\CheckBonus',
        'checkBonusDaily'  =>  'app\common\command\checkBonusDaily',
        'DailyMonthReward'  =>  'app\common\command\DailyMonthReward',

        'YuebaoBonus'  =>  'app\common\command\YuebaoBonus',
        'Test'  =>  'app\common\command\Test',
        'backup'  =>  'app\common\command\Backup',

        'GaoJiYongHuDay'  =>  'app\common\command\GaoJiYongHuDay',
        'GaoJiYongHuMonth'  =>  'app\common\command\GaoJiYongHuMonth',
        'GaoJiYongHu'  =>  'app\common\command\GaoJiYongHu',

        'Sengyuka'  =>  'app\common\command\Sengyuka',
        'qingling5'  =>  'app\common\command\Qingling5',//发放观看直播奖励（累计≥30分钟）

        'Test1'  =>  'app\common\command\Test1',
        'sync:wallet' => 'app\common\command\SyncWalletToBalance',
        'sendMonthlyBonus' => 'app\common\command\SendMonthBonus',//健康服务项目收益发放
        'sendMonthlyYanglao' => 'app\common\command\SendMonthYanglao',//养老服务项目收益发放 （废弃）
        'sendMonthlyYanglaoOne' => 'app\common\command\SendMonthYanglaoOne',//养老服务项目收益发放1 7月19日以前流转的会员，也全部在7月26日统一发完20天的日收益及到期收益。
        'sendMonthlyYanglaoTwo' => 'app\common\command\SendMonthYanglaoTwo',//养老服务项目收益发放2 7月19日以后流转的会员不发日收益，只在7月26日发一笔到期收益就可以

        'autoReviewAudit' => 'app\common\command\AutoReviewAudit',//自动审核超时的审核记录
        'transferRechargeToRed' => 'app\common\command\TransferTopupbalance',//将所有用户的充值余额转移到红包余额
        'sendTeamLeaderSalary' => 'app\common\command\SendTeamLeaderSalary',//每月1号发放团队长月薪工资
        'selectImportUser' => 'app\common\command\SelectImportUser',//导出最近五天都未登入的会员，按团队三级总人数排序下来
        'replaceIdCardFrontDomain' => 'app\common\command\ReplaceIdCardFrontDomain',//将 mp_audit_records 表中 id_card_front 字段的旧域名替换为新域名
    ],
];
