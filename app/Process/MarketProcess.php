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


class MarketProcess extends AbstractProcess
{
    public function handle(): void
    {
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $db = ApplicationContext::getContainer()->get(DB::class);

        $logger->info("火币进程启动");

        $symbols = $db->query('SELECT * FROM `contract_symbols` WHERE status = ?;', [1]);

        $logger->info("交易对总数:" . count($symbols));

        foreach ($symbols as $symbol) {
            $this->createClient($symbol['symbol'], $logger);
        }
    }

    protected function createClient($symbol, $logger)
    {
        $logger->info(sprintf("【%s】交易对重连", $symbol));

        \Hyperf\Utils\Coroutine::create(function () use ($symbol, $logger) {

            try {
                $client = $this->getClient();

                if ($client) {
                    $id = Carbon::now()->getPreciseTimestamp(6);

                    $trade = [
                        'sub' => 'market.' . $symbol . '.trade.detail',
                        'id'  => $id
                    ];

                    $client->push(json_encode($trade));

                    $i = 0;

                    while (true) {

                        $statusCode = $client->getStatusCode();

                        if ($statusCode < 0) {
                            // 重连
                            Log::get()->error(sprintf("【%s】【%d】交易对连接失败:%s, %d, %s", $symbol, Coroutine::id(), $client->getStatusCode(), $client->errCode,  socket_strerror($client->errCode)));
                            $ret = $client->close();
                            Log::get()->info(sprintf("【%s】【%d】交易对连接断开:%s", $symbol, Coroutine::id(), $ret));
                            break;
                        }

                        $msg = $client->recv(10);

                        if ($msg && $msg->data) {

                            $data = gzdecode($msg->data);

                            if ($data) {

//                                $logger->info(sprintf("【%d】【%s】交易对交易详情: %s", Coroutine::id(), $symbol, $data));

                                $data = json_decode($data, TRUE);

                                if (isset($data['ping'])) {
                                    $logger->info(sprintf("【%d】: %s", Coroutine::id(), json_encode([
                                        'pong'  => $data['ping']
                                    ])));
                                    $client->push(json_encode([
                                        'pong'  => $data['ping']
                                    ]));
                                }

                                if (isset($data['ch'])) {
                                    if (strpos($data['ch'], '.') > 0) {
                                        $topic = explode('.', $data['ch']);

                                        if ($topic[2] == 'trade') {
                                            $data = $data['tick']['data'];

                                            $redis = ApplicationContext::getContainer()->get(Redis::class);

                                            $redis->hSet('market.prices', $symbol, $data[0]['price']);
                                        }
                                    }
                                }

                                if ($i >= 1000) {
                                    $client->push('{"ping":' . Carbon::now()->getPreciseTimestamp(3) . '}');
                                    $i = 0;
                                }

                            }
                        }

                        $i++;

                        Coroutine::sleep(0.01);
                    }
                }
            } catch (\Exception $e) {
                $logger->error(sprintf("【%s】交易对请求失败: %s", $symbol, $e->getMessage()));
            }

            defer(function () use ($symbol, $logger){
                $this->createClient($symbol, $logger);
            });

        });

    }

    protected function getClient()
    {
        $client = new Client(env('HUOBI_API_HOST'), 443, true);
        $ret = $client->upgrade('/ws');

        if (!$ret) {
            throw new \Exception("websocket upgrade 失败");
        }

        return $client;
    }

}