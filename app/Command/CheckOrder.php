<?php

declare(strict_types=1);

namespace App\Command;

use App\Helpers\MyCache;
use App\Model\ContractOrder;
use App\Model\ContractPosition;
use App\Model\User;
use App\Services\ContractService;
use App\Services\SenderService;
use App\Utils\HashId;
use App\Utils\MyNumber;
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
class CheckOrder extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:order');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('检查当前委托');
    }

    public function handle()
    {
        $db = $this->container->get(DB::class);
        $sender = $this->container->get(SenderService::class);
        $redis = $this->container->get(\Hyperf\Redis\Redis::class);
        $logger = $this->container->get(StdoutLoggerInterface::class);
        $contractService = $this->container->get(ContractService::class);

        // 获取所有的仓位
        $orders = ContractOrder::with('index', 'user')
            ->where('status', '=', 0)
            ->get();

        $new_orders = $orders->groupBy('user_id');

        foreach ($new_orders as $k => $v) {

            $fd = $redis->hGet('ws.user.fds', (string)$k);

            if ($fd && $fd > 0) {
                $push_data = [];

                foreach ($v as $kk => $vv) {
                    $push_data[] = [
                        'id'           => HashId::encode($vv->id),
                        'direction'    => $vv->direction,
                        'price'        => BigDecimal::of($vv->price)->toScale($vv->index->price_decimal),
                        'volume'       => $vv->volume,
                        'trade_volume' => $vv->trade_volume,
                        'price_type'   => $vv->price_type,
                        'fee'          => MyNumber::formatSoke($vv->fee),
                        'created_at'   => $vv->created_at,
                        'lever'        => $vv->lever,
                        'index'        => [
                            'id'        => HashId::encode($vv->index_id),
                            'title'     => $vv->index->title,
                            'sub_title' => $vv->index->sub_title,
                        ]
                    ];
                }

                $push_data = json_encode([
                    'ch'   => "index.orders",
                    'ts'   => Carbon::now()->getPreciseTimestamp(3),
                    'data' => $push_data
                ]);

                $logger->info("推送委托:" . $push_data);

                $sender->push((int)$fd, $push_data);
            }
        }
    }

}
