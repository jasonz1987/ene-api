<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Model\Order;
use App\Model\ProfitLog;
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

class Withdraw extends Job
{
    public $params;

    /**
     * 任务执行失败后的重试次数，即最大执行次数为 $maxAttempts+1 次
     *
     * @var int
     */
    protected $maxAttempts = 0;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        if (!$this->params) {
            return;
        }

        $log = ProfitLog::find($this->params);

        if (!$log) {
            return;
        }

        if ($log->status > 0) {
            return;
        }

        try {
            $clientFactory  = ApplicationContext::getContainer()->get(ClientFactory::class);

            $options = [];
            // $client 为协程化的 GuzzleHttp\Client 对象
            $client = $clientFactory->create($options);

            $amount = BigDecimal::of($log->amount)->toScale(6, RoundingMode::DOWN);

            $url = sprintf('http://localhost:3001?to=%s&amount=%s', $log->user->address, (string)$amount);

            $response = $client->request('GET', $url);

            if ($response->getStatusCode() == 200) {
                Log::get()->info(sprintf('提现成功：%s' , $response->getBody()->getContents()));
                $body = json_decode($response->getBody()->getContents(), true);
                if ($body['code'] == 200) {
                    $log->tx_id = $body['data']['txId'];
                    $log->save();
                } else {
                    $log->error = $body['message'];
                    $log->save();
                    Log::get()->error(sprintf('提现失败：%s' ,$body['message']));
                }
            } else {
                Log::get()->error(sprintf('提现失败：%s' ,$response->getStatusCode()));
            }
        } catch (\Exception $e) {
            Log::get()->error(sprintf('提现失败：%s' , $e->getMessage()));
        }


    }
}