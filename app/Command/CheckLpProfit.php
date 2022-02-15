<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\LpProfitLog;
use App\Model\ProfitLog;
use App\Model\User;
use App\Service\QueueService;
use App\Services\ConfigService;
use App\Services\EthService;
use App\Services\UserService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use http\Exception\RuntimeException;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;

/**
 * @Command
 */
class CheckLpProfit extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:lp-profit');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('检查LP收益');
    }

    public function handle()
    {
        $ethService = make(EthService::class);

        $logs = LpProfitLog::where('status', '=', 0)
            ->where('created_at', '<=', Carbon::now()->subMinutes(5))
            ->get();

        foreach ($logs as $log) {

            $transaction = $ethService->getTransactionReceipt($log->tx_id);

            if ($transaction) {
                if (hexdec($transaction->status) == 1) {
                    $log->status = 1;
                    $log->save();
                } else {
                    $log->status = 2;
                    $log->error = '交易失败';
                    $log->save();
                }
            } else {
                $log->error = '未查询到交易';
                $log->status = 2;
                $log->save();
            }
        }

    }

}
