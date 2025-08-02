<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'public'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        
        // 更多的磁盘配置信息
        'qiniu' =>[									//完全可以自定义的名称
            'type'=>'qiniu',						//可以自定义,实际上是类名小写
            'accessKey' =>'gagk0DqbsTzZlyxEA7pf46GUajqXqD0oiROotXOh',		//七牛云的配置,accessKey
            'secretKey'=>'ymzPeW8BIs9eGBpx-2eMiqPwYBJ-PUhJOoqyCH-C',//七牛云的配置,secretKey
            'bucket'=>'xinjh1215gsdigwi4564a',					//七牛云的配置,bucket空间名
            //'domain'=>'s2dgpwe6t.hn-bkt.clouddn.com'					//七牛云的配置,domain,域名
//            'domain'=>'7niu.ybvgq.cn',				//七牛云的配置,domain,域名
            'domain'=>'77niu.cerhc.cn',				//七牛云的配置,domain,域名
        ],
    ],
];
