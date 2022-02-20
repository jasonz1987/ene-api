<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;
return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        (new Crontab())->setType('command')->setName('CheckDeposit')->setRule('* * * * *')->setCallback([
            'command' => 'check:deposit',
        ]),
        (new Crontab())->setType('command')->setName('CheckReferrer')->setRule('* * * * *')->setCallback([
            'command' => 'check:referrer',
        ]),
        (new Crontab())->setType('command')->setName('CheckProfit')->setRule('* * * * *')->setCallback([
            'command' => 'check:profit',
        ]),
        (new Crontab())->setType('command')->setName('CheckLpProfit')->setRule('* * * * *')->setCallback([
            'command' => 'check:lp-profit',
        ]),
        (new Crontab())->setType('command')->setName('GetPrice')->setRule('* * * * *')->setCallback([
            'command' => 'get:price',
        ]),
        (new Crontab())->setType('command')->setName('CheckStake')->setRule('* * * * *')->setCallback([
            'command' => 'check:stake',
        ]),
        (new Crontab())->setType('command')->setName('CheckCancelStake')->setRule('* * * * *')->setCallback([
            'command' => 'check:cancel-stake',
        ]),
        (new Crontab())->setType('command')->setName('GetLPBalance')->setRule('23 59 * * *')->setCallback([
            'command' => 'get:lp-balance',
        ]),
//        (new Crontab())->setType('command')->setName('CheckEvent')->setRule('*/5 * * * *')->setCallback([
//            'command' => 'check:event',
//        ]),
//        (new Crontab())->setType('command')->setName('DaoDraw')->setRule('00 16 * * *')->setCallback([
//            'command' => 'dao:draw',
//        ]),

//        (new Crontab())->setType('command')->setName('CheckDAO')->setRule('*/10 * * * * *')->setCallback([
//            'command' => 'check:dao',
//        ]),
    ],
];