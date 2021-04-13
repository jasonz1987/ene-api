<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;
return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        // Command类型定时任务
        (new Crontab())->setType('command')->setName('CreateKline')->setRule('* * * * * *')->setCallback([
            'command' => 'create:kline',
        ]),

        (new Crontab())->setType('command')->setName('CheckPower')->setRule('* * * * *')->setCallback([
            'command' => 'check:power',
        ]),

        (new Crontab())->setType('command')->setName('CheckPosition')->setRule('*/5 * * * * *')->setCallback([
            'command' => 'check:position',
        ]),

        (new Crontab())->setType('command')->setName('CheckOrder')->setRule('*/5 * * * * *')->setCallback([
            'command' => 'check:order',
        ]),
    ],
];