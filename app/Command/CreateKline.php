<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ContractIndex;
use App\Model\ContractOrder;
use App\Model\ContractPosition;
use App\Services\SenderService;
use App\Services\ContractService;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Coroutine;
use Hyperf\WebSocketServer\Sender;
use Illuminate\Support\Facades\Redis;
use Psr\Container\ContainerInterface;

/**
 * @Command
 */
class CreateKline extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('create:kline');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('生成K线');
    }

    public function handle()
    {
        $redis = $this->container->get(\Hyperf\Redis\Redis::class);
        $logger = $this->container->get(StdoutLoggerInterface::class);
        $contractService = $this->container->get(ContractService::class);

        $prices = $redis->hGetAll('market.prices');

        // 获取所有的指数
        $indexes = ContractIndex::where('status', '=', 1)
            ->get();

        foreach ($indexes as $index) {
            $this->createPrice($index, $prices, $redis, $logger, $contractService);
        }
    }

    protected function createPrice($index, $prices, $redis, $logger, $contractService)
    {
        $symbols = json_decode($index->symbols, true);

        $price = 0;

        foreach ($symbols as $symbol) {
            if (isset($prices[$symbol['symbol']])) {
                $price += $prices[$symbol['symbol']] * $symbol['index'];
            } else {
                $logger->error("交易对价格信息不存在:" . $symbol['symbol']);
            }
        }

        if ($price > 0) {

            $price = round($price, $index->price_decimal);

            // 获取订单
            $buy_ids = $contractService->getIndexOrders($index, $price, 'buy');
            $sell_ids = $contractService->getIndexOrders($index, $price, 'sell');

            $ids = array_merge($buy_ids, $sell_ids);

            // 获取对应的订单记录
            $orders = ContractOrder::with('user', 'index')
                ->whereIn('id', $ids)
                ->get();

            foreach ($orders as $order) {
                if ($contractService->addOrderLock($order)) {
                    // 判断订单状态
                    if ($order->status != 0) {
                        // 移除订单队列
                        $contractService->removeIndexOrder($index->code, $order->direction, $order->id);
                        continue;
                    }

                    // 判断仓位的状态
                    Db::beginTransaction();

                    try {
                        $order->trade_price = $order->price;
                        $order->trade_volume = $order->volume;
                        $order->save();

                        // 更新仓位
                        $contractService->updatePosition($order);

                        // 更新余额

                        Db::commit();

                        // 更新对应仓位的未实现收益

                    } catch (\Exception $e) {
                        Db::rollBack();

                    }

                }
            }


            // 设置最新价格
            $redis->hSet('index.prices', $index->code, $price);

            $periods = ['1min', '5min', '15min', '30min', '60min', '4hour', '1day', '1week', '1mon'];

            $kline_data = [];

            foreach ($periods as $period) {

                switch ($period) {
                    case '1min':
                        $time = strtotime(date('Y-m-d H:i' . ':00'));
                        break;
                    case '5min':
                        $i = date('i');
                        $m = (int)(5 * floor($i / 5));
                        $time = strtotime(date('Y-m-d H:' . $m));
                        break;
                    case '15min':
                        $i = date('i');
                        $m = (int)(15 * floor($i / 15));
                        $time = strtotime(date('Y-m-d H:' . $m . ':00'));
                        break;
                    case '30min':
                        $i = date('i');
                        $m = (int)(30 * floor($i / 30));
                        $time = strtotime(date('Y-m-d H:' . $m . ':00'));
                        break;
                    case '60min':
                        $time = strtotime(date('Y-m-d H' . ':00:00'));
                        break;
                    case '4hour':
                        $h = date('H');
                        $h = (int)(4 * floor($h / 4));
                        $time = strtotime(date('Y-m-d ' . $h . ':00:00'));
                        break;
                    case '1day':
                        $time = strtotime(date('Y-m-d' . ' 00:00:00'));
                        break;
                    case '1week':
                        $time = strtotime('monday this week');
                        break;
                    case '1mon':
                        $time = strtotime(date('Y-m-' . '00 00:00:00'));
                        break;
                }

                $cache_data = $redis->hGet("index.kline." . $index->code . '.' . $period, strval($time));

//                $random_volume = mt_rand(1, 10);

                // 对比缓存和现在的价格
                if ($cache_data) {
                    $cache_data = unserialize($cache_data);

                    $tick_data = [
                        'open'      => $cache_data['open'],
                        'low'       => min($cache_data['low'], $price),
                        'close'     => $price,
                        'high'      => max($cache_data['high'], $price),
//                        'count'     => $cache_data['count'] + 1,
//                        'volume'    => $cache_data['volume'] + $random_volume,
//                        'amount'    => $cache_data['amount'] + $random_volume,
//                        'turnover'  => $cache_data['turnover'] + round($price * $random_volume, $index->price_decimal),
                        'timestamp' => $time
                    ];

                } else {

                    $tick_data = [
                        'open'      => $price,
                        'low'       => $price,
                        'close'     => $price,
                        'high'      => $price,
//                        'volume'    => $random_volume,
//                        'amount'    => $random_volume,
//                        'count'     => 1,
//                        'turnover'  => round($price * $random_volume, $index->price_decimal),
                        'timestamp' => $time
                    ];
                }

                $redis->hSet("index.kline." . $index->code . '.' . $period, strval($time), serialize($tick_data));

                $kline_data[$period] = $tick_data;
            }

            $fds = $redis->hKeys('ws.fd.users');

            $today = null;

            // 推送K线
            if ($kline_data) {
                $today = $kline_data['1day'];

                $kline_data = json_encode([
                    'ch'   => "index.kline." . $index->code,
                    'ts'   => Carbon::now()->getPreciseTimestamp(3),
                    'data' => $kline_data
                ]);

                $logger->info("推送K线:" . $kline_data);

                foreach ($fds as $fd) {
                    Coroutine::create(function () use ($fd, $kline_data) {
                        $sender = $this->container->get(Sender::class);
                        $sender->push((int)$fd, $kline_data);
                    });
                }
            }

            // 推送行情
            if($today) {
                $trade_volume = ContractOrder::where('status', '=', 1)
                    ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
                    ->sum('volume');

                // 获取持仓量
                $position_volume = ContractPosition::where('status', '=', 1)
                    ->sum('position_volume');

                $market_data = json_encode([
                    'ch'   => "index.market." . $index->code,
                    'ts'   => Carbon::now()->getPreciseTimestamp(3),
                    'data' => [
                        'last_price'      => $price,
                        'open_price'      => strval($today['open']),
                        'quote_change'    => $contractService->getIndexQuoteChange($index->code),
                        'today_high'      => $today ? strval($today['high']) : null,
                        'today_low'       => $today ? strval($today['low']) : null,
                        'trade_volume'    => $trade_volume * 10,
                        'position_volume' => $position_volume * 10,
                    ]
                ]);

                $logger->info("推送行情:" . $market_data);

                foreach ($fds as $fd) {
                    Coroutine::create(function () use ($fd, $market_data) {
                        $sender = $this->container->get(Sender::class);
                        $sender->push((int)$fd, $market_data);
                    });
                }
            }
        }
    }
}
