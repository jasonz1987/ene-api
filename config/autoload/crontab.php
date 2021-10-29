<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;
return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
//        (new Crontab())->setType('command')->setName('QueryEvent')->setRule('* * * * *')->setCallback([
//            'command' => 'query:event',
//        ]),
        (new Crontab())->setType('command')->setName('CheckProfit')->setRule('* * * * *')->setCallback([
            'command' => 'check:profit',
        ]),
//        (new Crontab())->setType('command')->setName('CheckDeposit')->setRule('* * * * *')->setCallback([
//            'command' => 'check:deposit',
//        ]),
//        (new Crontab())->setType('command')->setName('CheckEvent')->setRule('*/5 * * * *')->setCallback([
//            'command' => 'check:event',
//        ]),
//        (new Crontab())->setType('command')->setName('DaoDraw')->setRule('00 16 * * *')->setCallback([
//            'command' => 'dao:draw',
//        ]),
        (new Crontab())->setType('command')->setName('CheckBurn')->setRule('* * * * *')->setCallback([
            'command' => 'check:burn',
        ]),
        (new Crontab())->setType('command')->setName('CheckWxProfit')->setRule('* * * * *')->setCallback([
            'command' => 'check:wx-profit',
        ]),
//        (new Crontab())->setType('command')->setName('CheckDAO')->setRule('*/10 * * * * *')->setCallback([
//            'command' => 'check:dao',
//        ]),
    ],
];