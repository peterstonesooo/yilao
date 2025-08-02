<?php

return array(
    // '常规管理' =>
    //     array(
    //         'icon' => 'fa-cubes',
    //         'url' =>
    //             array(
    //                 '登记数据' =>
    //                     array(
    //                         'icon' => 'fa-circle-o',
    //                         'url' => 'admin/User/promote',
    //                     ),
    //             ),
    //     ),







    '控制台' =>
        array(
            'icon' => 'fa-home',
            'url' => 'admin/Home/index',
        ),
    '常规管理' =>
        array(
            'icon' => 'fa-cubes',
            'url' =>
                array(
                    // '基金申报记录' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/JijinOrder/list',
                    // ),
                    // '原资产申报记录' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/YuanOrder/list',
                    // ),
                    // '释放提现进度表' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/ReleaseWithdrawal/list',
                    // ),
                    // '新幸运大抽奖设置' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Huodong/setting',
                    // ),
                    // '幸运大抽奖记录' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Huodong/SigninLog',
                    // ),
                    // '登记数据' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/User/promote',
                    //     ),
                     '会员实名认证' =>
                         array(
                             'icon' => 'fa-circle-o',
                             'url' => 'admin/User/authentication',
                         ),
                    // '签到转盘奖励设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Signin/setting',
                    //     ),
                    '签到记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Signin/SigninLog',
                        ),
//                    '审核记录' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Audit/AuditRecords',
//                        ),
                    '会议列表' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Meetings/list',
                        ),
                    '会议签到记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/MeetingRecords/list',
                        ),
                    '会议奖励配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/MeetingRecords/settingList',
                        ),
                    '自动审核列表' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/AuditRecords/auditList',
                        ),
//                    '蛇年红包奖品设置' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Signin/setting',
//                        ),
//                    '蛇年红包奖品抽奖记录' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Signin/SigninPrizeLog',
//                        ),
//                    '内定人员列表' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Signin/LuckyUser',
//                        ),
                    // '红包设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/RedEnvelope/setting',
                    //     ),
                    // '红包领取记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/RedEnvelope/userLog',
                    //     ),
                    // '圆梦通道设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Yuanmeng/setting',
                    //     ),
                    // '圆梦登记记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Yuanmeng/userLog',
                    //     ),
                    // '三农补贴' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Butie/setting',
                    //     ),
                    // '三农补贴申请记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Butie/order',
                    //     ),
                    // '三农红利' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Hongli/setting',
                    //     ),
                    // '三农红利申请记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Hongli/order',
                    //     ),
                    // '公证记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Cert/order',
                    //     ),
                    // '保证金记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Cert/deposit',
                    //     ),
                    // '房产税记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Cert/fangchan',
                    //     ),
                    // '许愿树设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/WishTree/setting',
                    //     ),
                    // '许愿树领奖记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/WishTree/WishTreePrizeLog',
                    //     ),
                    // '龙头币' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Coin/list',
                    //     ),
                    // '龙头币K线图' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Coin/klineChart',
                    //     ),
                    // '龙头币持币列表' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Coin/coinHold',
                    //     ),
                    // '龙头币转账记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Coin/transferLog',
                    //     ),
                     '排行榜' =>
                         array(
                             'icon' => 'fa-circle-o',
                             'url' => 'admin/Ranking/list',
                         ),
                    '后台账号管理' =>
                        array(
                            'icon' => 'fa-users',
                            'url' => 'admin/AdminUser/adminUserList',
                        ),
                ),
        ),
        '交易管理' =>
        array(
            'icon' => 'fa-cubes',
            'url' =>
                array(
                    '项目管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Project/projectList',
                        ),
/*                     '项目管理二期' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Projects/projectsList',
                        ), */
                    '交易订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Order/orderList',
                        ),
//                    '升级缴费订单' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Order4/orderList',
//                        ),
//                    '赠送订单' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Order/orderListFree',
//                        ),
//                    '资金来源证明' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Order5/orderList',
//                        ),
                    // '开户订单' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/UserOpen/userBalanceLogList',
                    //     ),
                    // '修改分红天数'=>array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Order/addTime',
                    // ),

               	    // '流程审核' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Process/processList',
                    //     ),
                    // '项目管理一期' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Project1/projectList',
                    // ),
                    // '产品管理' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/product/productList',
                    // ),
//                    '购买产品记录' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/Userproduct/userProductList',
//                        ),
                ),
        ),
    '用户管理' =>
        array(
            'icon' => 'fa-user',
            'url' =>
                array(
                    '用户管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/userList',
                        ),
                    '银行签约列表' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/openSignatoryList',
                        ),
                    '用户资金明细' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/UserBalanceLog/userBalanceLogList',
                        ),
                    'wallet余额导入数据明细' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/UserBalanceLog/userWalletBalanceLogList',
                        ),
                    '余额导入' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/import',
                        ),
                    '生育卡' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Card/cardList',
                        ),
                    // '用户积分记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/UserBalanceLog/userIntegralLogList',
                    //     ),
                    // '收货地址' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/UserDelivery/userDeliveryList',
                    // ),
                ),
        ),
    '充值管理' =>
        array(
            'icon' => 'fa-gears',
            'url' =>
                array(
                '充值记录' =>
                    array(
                        'icon' => 'fa-circle-o',
                        'url' => 'admin/Capital/topupList',
                    ),
                '提现记录' =>
                    array(
                        'icon' => 'fa-circle-o',
                        'url' => 'admin/Capital/withdrawList',
                    ),
                ),
        ),
    '设置中心' =>
        array(
            'icon' => 'fa-gears',
            'url' =>
                array(
                    '支付渠道配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/PaymentConfig/paymentConfigList',
                        ),
                    // '股权K线图' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/KlineChart/klineChart',
                    //     ),
                    // '会员等级管理' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/LevelConfig/levelConfigList',
                    //     ),
                    // '轮播图设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Banner/bannerList',
                    //     ),
                    // '公司动态' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/SystemInfo/companyInfoList',
                    //     ),
                    '系统信息设置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/SystemInfo/systemInfoList',
                        ),
                    '常规配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Setting/settingList',
                        ),
                ),
        ),


//    '角色管理' =>
//        array(
//            'icon' => 'fa-gears',
//            'url' =>
//                array(
//                    '角色列表' =>
//                        array(
//                            'icon' => 'fa-circle-o',
//                            'url' => 'admin/AuthGroup/authGroupList',
//                        ),
//
//                ),
//        ),

);
