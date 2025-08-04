<?php
return [
    // 默认缓存驱动
    'default' => env('CACHE_DRIVER', 'redis'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',  // 缓存文件存放目录
            'prefix'     => '',
            'expire'     => 0,   // 默认缓存有效期（秒）
            'tag_prefix' => 'tag:',
            'serialize'  => [],  // 序列化方法
        ],
        'redis' => [
            'type'       => 'Redis',
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'timeout'    => 0,
            'prefix'     => '',
            'expire'     => 0,
        ],
    ],
];