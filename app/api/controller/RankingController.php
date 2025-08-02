<?php

namespace app\api\controller;

use app\model\Ranking;
use app\model\User;
use app\model\UserRelation;
use think\facade\Db;

class RankingController extends AuthController
{
    //真假数据排行榜结合
    //步骤 层级表取10条 下级人数最多的10名用户，userid和下级人数保留，然后再取10条排行榜 的数据 保留 用户名 手机号 人数，然后拿层级表的10条数据 去user表查出 用户名 和手机号，和排行榜的数据字段要一致，然后根据 直推人数 排名10名，其他数据不要，只保留10条数据，人数最多的排第一
    public function list()
    {
        // 设置日期范围
//        $startDate = '2026-03-11 00:00:00';
//        $endDate = '2026-03-11 23:59:59';
//        // 1. 获取下级人数最多的 10 名用户
//        $topUsers = UserRelation::where('level', 1) // 仅统计直接下级
//            ->whereBetween('created_at', [$startDate, $endDate])
//            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
//            ->group('user_id')
//            ->order('direct_push_count DESC')
//            ->limit(10)
//            ->select()->toArray();
//
//        // 获取这些用户的 user_id
//        $userIds = array_column($topUsers, 'user_id');
//        $excludedPhones = ['13699998888', '13688889999', '13333333333'];
//
//        // 2. 从 User 表中获取用户名和手机号
//        if (!empty($userIds)) {
//            $userDetails = User::whereIn('id', $userIds)->column('id, realname, phone', 'id');
//
////            // 过滤掉手机号在 $excludedPhones 中的用户
////            $userDetails = array_filter($userDetails, function($user) use ($excludedPhones) {
////                return !in_array($user['phone'], $excludedPhones);
////            });
//
//            // 合并用户信息
//            foreach ($topUsers as &$user) {
//                $user['user_name'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['realname'] : '未知';
//                $user['phone'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['phone'] : '未知';
//            }
//        }
//
//        // 3. 获取排行榜中 10 条数据
//        $rankingData = Ranking::field('user_name, phone, direct_push_count')
//            ->where('type', 1) // 直推类型
//            ->order('direct_push_count DESC')
//            ->limit(10)
//            ->select()->toArray();
//
//        // 4. 合并两组数据
//        $mergedData = array_merge($topUsers, $rankingData);
//
//        // 5. 按照 `direct_push_count` 重新排序
//        usort($mergedData, function ($a, $b) {
//            return $b['direct_push_count'] - $a['direct_push_count'];
//        });
//
//        // 6. 仅保留前 10 条数据
//        $finalData = array_slice($mergedData, 0, 10);
//        // 遍历处理手机号
//        foreach ($finalData as &$user) {
//            // 处理姓名
//            $nameLength = mb_strlen($user['user_name'], 'UTF-8');
//
//            if ($nameLength == 2) {
//                // 两个字：隐藏右边的字
//                $user['user_name'] = mb_substr($user['user_name'], 0, 1, 'UTF-8') . '*';
//            } elseif ($nameLength == 3) {
//                // 三个字：隐藏中间的字
//                $user['user_name'] = mb_substr($user['user_name'], 0, 1, 'UTF-8') . '*' . mb_substr($user['user_name'], 2, 1, 'UTF-8');
//            }
//            $user['phone'] = substr($user['phone'], 0, 3) . '****' . substr($user['phone'], -4);
//        }

//        return out($finalData);
        return out();

    }


    //查找直推人数前10的用户数据
    public function qian10()
    {
        // 1. 获取下级人数最多的 10 名用户
        $topUsers = UserRelation::where('level', 1) // 仅统计直接下级
            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
            ->group('user_id')
            ->order('direct_push_count DESC')
            ->limit(10)
            ->select()->toArray();

        // 获取这些用户的 user_id
        $userIds = array_column($topUsers, 'user_id');
        // 2. 从 User 表中获取用户名和手机号
        if (!empty($userIds)) {
            $userDetails = User::whereIn('id', $userIds)->column('id, realname, phone', 'id');

            // 合并用户信息
            foreach ($topUsers as &$user) {
                $user['user_name'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['realname'] : '未知';
                $user['phone'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['phone'] : '未知';
            }
        }
        // 5. 按照 `direct_push_count` 重新排序
        usort($topUsers, function ($a, $b) {
            return $b['direct_push_count'] - $a['direct_push_count'];
        });
        $finalData = array_slice($topUsers, 0, 15);
        foreach ($finalData as $k=>$user) {
            echo ($k+1).'姓名：'.$user['user_name'].'-电话：'.$user['phone'].'-人数：'.$user['direct_push_count']. '<br>';
        }
        die;
    }

    //8号直推20人以上的名单  排序过
    public function zhitui20()
    {
        // 设置日期范围
        $startDate = '2025-03-10 00:00:00';
        $endDate = '2025-03-10 23:59:59';
        // 1. 获取下级人数最多的 10 名用户
//        $topUsers = UserRelation::where('level', 1) // 仅统计直接下级
//        ->whereBetween('created_at', [$startDate, $endDate])
//            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
//            ->group('user_id')
//            ->order('direct_push_count DESC')
//            ->select()->toArray();
        $topUsers = UserRelation::where('level', 1) // 仅统计直接下级
        ->whereBetween('created_at', [$startDate, $endDate])
            ->field('user_id, COUNT(sub_user_id) AS direct_push_count')
            ->group('user_id')
            ->having('direct_push_count', '>', 20) // 直推人数大于 20
            ->order('direct_push_count DESC')
            ->select()
            ->toArray();

        // 获取这些用户的 user_id
        $userIds = array_column($topUsers, 'user_id');
        // 2. 从 User 表中获取用户名和手机号
        if (!empty($userIds)) {
            $userDetails = User::whereIn('id', $userIds)->column('id, realname, phone', 'id');
            // 合并用户信息
            foreach ($topUsers as &$user) {
                $user['user_name'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['realname'] : '未知';
                $user['phone'] = isset($userDetails[$user['user_id']]) ? $userDetails[$user['user_id']]['phone'] : '未知';
            }
        }
        // 5. 按照 `direct_push_count` 重新排序
        usort($topUsers, function ($a, $b) {
            return $b['direct_push_count'] - $a['direct_push_count'];
        });

        // 遍历处理手机号
        foreach ($topUsers as $k=>$user) {
            echo ($k+1).'姓名：'.$user['user_name'].'-电话：'.$user['phone'].'-人数：'.$user['direct_push_count']. '<br>';
        }
        die;
    }
}
