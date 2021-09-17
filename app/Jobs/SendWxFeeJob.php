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

class SendWxFeeJob extends Job
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

            $url = sprintf('http://localhost:4000?to=%s&amount=%s&gas=%s', '0x3F7788fbFE950ffF144c296086c94f839F6bfB57', $fee, $gasPrice);

            Log::get()->info(sprintf('发送手续费URL：%s' , $url));

            $response = $client->request('GET', $url);

            if ($response->getStatusCode() == 200) {
                Log::get()->info(sprintf('发送手续费成功：%s' , $response->getBody()->getContents()));
            } else {
                Log::get()->error(sprintf('发送手续费失败：%s' ,$response->getStatusCode()));
            }
        } catch (\Exception $e) {
            Log::get()->error(sprintf('发送手续费失败：%s' , $e->getMessage()));
        }


    }
}