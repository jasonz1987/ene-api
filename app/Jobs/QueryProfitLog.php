<?php

namespace App\Jobs;

use App\Model\ProfitLog;
use App\Services\EthService;
use App\Utils\Log;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\AsyncQueue\Job;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\ApplicationContext;
use Web3\Utils;

class QueryProfitLog extends Job
{

    protected $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $log = ProfitLog::find($this->params);

        if (!$log) {
            return;
        }

        if ($log->status > 1) {
            return;
        }

        if (!$log->tx_id) {
            return;
        }

        // 查询交易
        $ethService = new EthService();

        Db::beginTransaction();

        try {
            $transaction = $ethService->getTransactionReceipt($log->tx_id);

            if ($transaction) {
                if (hexdec($transaction->status) == 1) {

                    $receipt = $ethService->decodeReceipt($transaction);
                    $real_amount = BigDecimal::of($receipt['value'])->dividedBy(10 ** 18, 6, RoundingMode::DOWN);

                    $log->status = 2;
                    $log->confirmed_at = Carbon::now();
                    $log->real_amount = $real_amount;
                    $log->save();

                    Db::commit();

                } else {
                    $log->status = 3;
                    $log->error = '区块交易失败';
                    $log->save();
                }
            }
        } catch (\Exception $e) {

            Db::rollBack();

            Log::error("查询算力奖励失败", [
                'log'   =>  $log,
                'error' =>  $e->getMessage()
            ]);
        }
    }

}
