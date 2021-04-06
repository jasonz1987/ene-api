<?php

declare(strict_types=1);

namespace App\Command;

use App\Helpers\MyCache;
use App\Model\ContractPosition;
use App\Model\User;
use App\Services\ContractService;
use App\Utils\HashId;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
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
        $db = $this->container->get(DB::class);
        $sender = $this->container->get(Sender::class);
        $redis = $this->container->get(\Hyperf\Redis\Redis::class);
        $logger = $this->container->get(StdoutLoggerInterface::class);
        $contractService = $this->container->get(ContractService::class);

        $prices = $redis->hGetAll('market.prices');

        // 获取所有的仓位
//        $positions = $db->query('SELECT `contract_positions`.*, `contract_indexes`.code FROM `contract_positions` inner join `contract_indexes` on `contract_poistions`.index_id = `contract_indexes` . id WHERE status = ?;', [1]);
        $positions = ContractPosition::with('index', 'user')
            ->where('status', '=', 1)
            ->get();


        $new_positions = $positions->groupBy('user_id');

        foreach ($new_positions as $k=>$v) {

            $total_profit = BigDecimal::zero();
            $total_amount = BigDecimal::zero();

            $ids = [];

            $push_data = [];

            // 获取全部的收益
            foreach ($v as $kk=>$vv) {
                $user = $vv->user;
                $ids[] = $vv->id;
                $total_amount = $total_amount->plus($vv->position_amount);
                $profit = $contractService->getUnrealProfit($vv);
                // 计算收益
                if (BigDecimal::of($vv)->isGreaterThan(0)) {
                    $total_profit = $total_profit->plus($profit);
                } else {
                    $total_profit = $total_profit->minus($profit);
                }

                $rate = $profit->dividedBy($vv->position_amount, 4, RoundingMode::UP);

                $push_data[] = [
                    'id'                => HashId::encode($vv->id),
                    'direction'         => $vv->direction,
                    'position_volume'   => $vv->position_volume,
                    'open_price'        => BigDecimal::of($vv->open_price)->toScale($vv->index->price_decimal),
                    'liquidation_price' => $this->contractService->getLiquidationPrice($vv),
                    'position_amount'   => BigDecimal::of($vv->position_amount)->toScale(6),
                    'profit_unreal'     => $profit,
                    'profit_rate'       => $rate,
                    'lever'             => $vv->lever,
                    'index'             => [
                        'id'        => HashId::encode($vv->index_id),
                        'title'     => $vv->index->title,
                        'sub_title' => $vv->index->sub_title,
                    ]
                ];
            }

            if($user) {
                if ($total_profit->isLessThan(0)) {

                    // 可用余额
                    $balance = BigDecimal::of($user->balance)->minus($user->frozen_balance);

                    if ($total_profit->abs()->isGreaterThan($balance)) {

                        ContractPosition::whereIn('id', $ids)
                            ->lockForUpdate()
                            ->get();

                        Db::beginTransaction();

                        try {

                            // 执行爆仓逻辑 加锁
                            ContractPosition::whereIn('id', $ids)
                                ->update([
                                    'status'    =>  0,
                                    'liquidation_type'  => 2
                                ]);

                            // 减去冻结的保证金
                            $user->decrement('balance_frozen', $total_amount);

                            Db::commit();

                        } catch (\Exception $e) {
                            Db::rollBack();
                            $logger->error(sprintf("爆仓失败:【%s】,%s", $user->id, $e->getMessage()));
                        }

                    }
                }

                // 推送仓位信息
                $fd = $redis->hGet('ws.user.fds', $k);
                $sender = $this->container->get(Sender::class);

                if ($fd) {
                    $push_data = json_encode([
                        'ch'   => "index.positions",
                        'ts'   => Carbon::now()->getPreciseTimestamp(3),
                        'data' => $push_data
                    ]);

                    $logger->info("推送持仓:" . $push_data);

                    $sender->push((int)$fd, $push_data);

                }
            }
        }
    }

}
