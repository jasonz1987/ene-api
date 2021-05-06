<?php

declare(strict_types=1);

namespace App\Command;

use App\Helpers\MyCache;
use App\Model\ContractPosition;
use App\Model\User;
use App\Services\ContractService;
use App\Services\SenderService;
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
class CheckPosition extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:position');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('检查仓位');
    }

    public function handle()
    {
        $db = $this->container->get(DB::class);
        $sender = $this->container->get(SenderService::class);
        $redis = $this->container->get(\Hyperf\Redis\Redis::class);
        $logger = $this->container->get(StdoutLoggerInterface::class);
        $contractService = $this->container->get(ContractService::class);

        // 获取所有的仓位
        $positions = ContractPosition::with('index', 'user')
            ->where('status', '=', 1)
            ->get();

        $new_positions = $positions->groupBy('user_id');

        foreach ($new_positions as $k => $v) {

//            $total_profit = BigDecimal::zero();
//            $total_amount = BigDecimal::zero();

            $ids = [];

            $push_data = [];

            // 获取全部的收益
            foreach ($v as $kk => $vv) {
                $user = $vv->user;
                $ids[] = $vv->id;

                $profit = $contractService->getUnrealProfit($vv);

//                // 计算收益
//                if (BigDecimal::of($profit)->isGreaterThan(0)) {
//                    $total_profit = $total_profit->plus($profit);
//                } else {
//                    $total_profit = $total_profit->minus($profit->abs());
//                }

                $rate = $profit->dividedBy($vv->position_amount, 4, RoundingMode::UP);

                $push_data[] = [
                    'id'                => HashId::encode($vv->id),
                    'direction'         => $vv->direction,
                    'position_volume'   => $vv->position_volume,
                    'open_price'        => BigDecimal::of($vv->open_price)->toScale($vv->index->price_decimal),
                    'liquidation_price' => $contractService->getLiquidationPrice($vv),
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

                if ($profit->isLessThan(0)) {

                    // 可用余额
                    $balance = BigDecimal::of($user->balance)->multipliedBy(0.995);

                    if ($profit->abs()->isGreaterThan($balance->plus($vv->position_amount))) {

                        Db::beginTransaction();

                        try {

                            $vv->status = 0;
                            $vv->profit = $balance->plus($vv->position_amount);
                            $vv->liquidate_type = 2;
                            $vv->save();

                            // 减去冻结的保证金
                            $user->frozen_balance = BigDecimal::of($user->frozen_balance)->minus(BigDecimal::of($vv->position_amount));
                            $user->balance = 0;
                            $user->save();

                            Db::commit();

                        } catch (\Exception $e) {
                            Db::rollBack();
                            $logger->error(sprintf("爆仓失败:【%s】,%s", $user->id, $e->getMessage()));
                        }
                    }
                }

            }

            if ($user) {

                // 推送仓位信息
                $fd = $redis->hGet('ws.user.fds', (string)$k);

                if ($fd && $fd > 0) {
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
