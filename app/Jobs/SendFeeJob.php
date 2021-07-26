<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Model\Order;
use App\Model\User;
use App\Service\MarketService;
use App\Service\OrderService;
use App\Service\QueueService;
use App\Service\SymbolService;
use App\Services\EthService;
use App\Services\UserService;
use App\Utils\Log;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\AsyncQueue\Job;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\ApplicationContext;

class SendFeeJob extends Job
{
    public $params;

    /**
     * 任务执行失败后的重试次数，即最大执行次数为 $maxAttempts+1 次
     *
     * @var int
     */
    protected $maxAttempts = 1;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        if (!$this->params) {
            return;
        }

        $fee = $this->params;

        $ethService = make(EthService::class);

        $gasPrice = $ethService->getGasPrice();

        if (!$gasPrice) {
            throw new \Exception("获取gasprice失败");
        }

        try {
            $clientFactory  = ApplicationContext::getContainer()->get(ClientFactory::class);

            $options = [];
            // $client 为协程化的 GuzzleHttp\Client 对象
            $client = $clientFactory->create($options);

            $url = sprintf('http://localhost:3000?to=%s&amount=%s&gas=%s', '0x3814ca95587de805ed14300068F3f1c3abFd8987', $fee, $gasPrice);

            $response = $client->request('GET', $url);

            if ($response->code == 200) {
                Log::get()->error(sprintf('发送手续费成功：' , $response->getBody()->getContents()));
            } else {
                Log::get()->error(sprintf('发送手续费失败：' ,$response->code));
            }
        } catch (\Exception $e) {
            Log::get()->error(sprintf('发送手续费失败：' , $e->getMessage()));
        }


    }
}