<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;
return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        (new Crontab())->setType('command')->setName('QueryEvent')->setRule('* * * * *')->setCallback([
            'command' => 'query:event',
        ]),
        (new Crontab())->setType('command')->setName('CheckProfit')->setRule('* * * * *')->setCallback([
            'command' => 'check:profit',
        ]),
        (new Crontab())->setType('command')->setName('CheckEvent')->setRule('*/5 * * * *')->setCallback([
            'command' => 'check:event',
        ]),
    ],
];