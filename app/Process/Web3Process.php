<?php
declare(strict_types=1);

namespace App\Process;

use App\Model\Strategy;
use App\Service\OrderService;
use App\Service\QueueService;
use App\Service\StrategyService;
use App\Service\SymbolService;
use App\Utils\Log;
use Carbon\Carbon;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DB\DB;
use Hyperf\Process\AbstractProcess;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;
use Hyperf\WebSocketClient\ClientFactory;
use Swoole\Coroutine\Http\Client;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;


class Web3Process extends AbstractProcess
{
    public function handle(): void
    {
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $db = ApplicationContext::getContainer()->get(DB::class);

        $abi = '';
        $address = '0xdac17f958d2ee523a2206206994597c13d831ec7';

        $web3 = new Web3(new HttpProvider(new HttpRequestManager('', 10)));


        $logger->info("WEB3进程启动");


    }

    protected function createClient($symbol, $logger)
    {


    }



}