<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;
return [
    'enable' => false,
    // 通过配置文件定义的定时任务
    'crontab' => [
        // Command类型定时任务
        (new Crontab())->setType('command')->setName('CreateKline')->setRule('* * * * * *')->setCallback([
            'command' => 'create:kline',
        ]),
    ],
];