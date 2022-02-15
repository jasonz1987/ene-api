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

class LpWithdraw extends Job
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

        $log = LpProfitLog::find($this->params);

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

            $url = sprintf('http://localhost:3002?to=%s&amount=%s', $log->user->address, (string)$amount);

            $response = $client->request('GET', $url);

            if ($response->getStatusCode() == 200) {
                $content = $response->getBody()->getContents();
                Log::get()->info(sprintf('LP提现成功：%s' , $content));
                $content = json_decode($content, true);
                if ($content['code'] == 200) {
                    $log->tx_id = $content['data']['txId'];
                    $log->save();
                } else {
                    $log->error = $content['message'];
                    $log->save();
                    Log::get()->error(sprintf('LP提现失败1：%s' ,$content['message']));
                }
            } else {
                Log::get()->error(sprintf('LP提现失败2：%s' ,$response->getStatusCode()));
            }
        } catch (\Exception $e) {
            Log::get()->error(sprintf('LP提现失败3：%s' , $e->getMessage()));
        }


    }
}