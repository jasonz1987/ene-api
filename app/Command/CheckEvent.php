<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\BurnLog;
use App\Model\User;
use App\Services\QueueService;
use App\Services\ConfigService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerInterface;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;

/**
 * @Command
 */
class CheckEvent extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:event');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('检查EVENT');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);

        $cache_block_number = $configService->getLastBlockNumber();

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));

        $abi = '[{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"power","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"cpuAmount","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"wxAmount","type":"uint256"}],"name":"Burn","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"burn","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"stateMutability":"nonpayable","type":"constructor"},{"inputs":[],"name":"cpuBurnAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"cpuBurnPercent","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"cpuBurnRate","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"cpuTokenAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getBurnCpuAndWx","outputs":[{"internalType":"uint256","name":"","type":"uint256"},{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"powerRate","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"totalCpuBurnAmount","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"totalWxBurnAmount","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"usdtCpuLpAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"usdtTokenAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"usdtWxLpAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"users","outputs":[{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"wxBurnAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"wxBurnPercent","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"wxBurnRate","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"wxTokenAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = env('BURN_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $events = $contract->getEvents();

        $eventName = 'Burn';

        $this->info("【检查进程】缓存区块");
        $this->info($cache_block_number);

        \App\Utils\Log::get()->info(sprintf("【检查进程】缓存区块:%s", $cache_block_number));

        $eventParameterNames = [];
        $eventParameterTypes = [];
        $eventIndexedParameterNames = [];
        $eventIndexedParameterTypes = [];

        foreach ($events[$eventName]['inputs'] as $input) {
            if ($input['indexed']) {
                $eventIndexedParameterNames[] = $input['name'];
                $eventIndexedParameterTypes[] = $input['type'];
            } else {
                $eventParameterNames[] = $input['name'];
                $eventParameterTypes[] = $input['type'];
            }
        }

        $numEventIndexedParameterNames = count($eventIndexedParameterNames);

        $eventSignature = $contract->getEthabi()->encodeEventSignature($events[$eventName]);
        $ethabi = $contract->getEthabi();

        \App\Utils\Log::get()->info("开始扫描...");

        $web3->getEth()->getLogs([
            'fromBlock' => '0x' . dechex($cache_block_number - 100),
            'toBlock' => '0x' . dechex($cache_block_number),
            'topics' => [$eventSignature],
            'address' => $contractAddress
        ],
            function ($err, $result) use (&$eventLogData, &$fromBlock, &$configService, &$latest_block_number, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                if($err !== null) {
                    \App\Utils\Log::get()->error(sprintf("【检查进程】扫描失败:%s", $err->getMessage()));
                    throw new \Exception($err->getMessage());
                }

                \App\Utils\Log::get()->info(sprintf("【检查进程】扫描的交易数:%s", count($result)));

                $hashes = [];

                foreach ($result as $k=>$object) {
                    $hashes[] = $object->transactionHash;
                }

                if (count($hashes) > 0) {

                    $logs = BurnLog::whereIn('tx_id', $hashes)
                        ->pluck('tx_id')->toArray();

                    foreach ($result as $k=>$object) {

                        if (in_array($object->transactionHash,$logs)) {
                            unset($result[$k]);
                        }
                    }

                    \App\Utils\Log::get()->info(sprintf("【检查进程】过滤后的交易数:%s", count($result)));

                    foreach ($result as $object) {

                        //decode the data from the log into the expected formats, with its corresponding named key
                        $decodedData = array_combine($eventParameterNames, $ethabi->decodeParameters($eventParameterTypes, $object->data));

                        //decode the indexed parameter data
                        for ($i = 0; $i < $numEventIndexedParameterNames; $i++) {
                            //topics[0] is the event signature, so we start from $i + 1 for the indexed parameter data
                            $decodedData[$eventIndexedParameterNames[$i]] = $ethabi->decodeParameters([$eventIndexedParameterTypes[$i]], $object->topics[$i + 1])[0];
                        }

                        Db::beginTransaction();

                        try {

                            $user = User::where('address', '=', $decodedData['user'])
                                ->first();

                            $is_upgrade_vip = false;

                            if ($user) {
                                $log = new BurnLog();
                                $log->user_id = $user->id;
                                $log->tx_id = $object->transactionHash;
                                $log->power = BigDecimal::of($decodedData['power'])->dividedBy(1e18,6, RoundingMode::DOWN);
                                $log->burn_cpu = BigDecimal::of($decodedData['cpuAmount'])->dividedBy(1e18,6, RoundingMode::DOWN);
                                $log->burn_wx = BigDecimal::of($decodedData['wxAmount'])->dividedBy(1e18,6, RoundingMode::DOWN);
                                $log->block_number = hexdec($object->blockNumber);
                                $log->save();

//                            $new_power = null;
//
//                            $contract->at($contractAddress)->call('userInfo', $user->address, [
//                                'from' => $user->address
//                            ], function ($err, $result) use (&$new_power) {
//                                if ($err !== null) {
//                                    throw new \Exception('获取用户算力失败');
//                                }
//
//                                $new_power = $result['power']->toString();
//                            });

//                            if ($new_power) {
                                $new_power = BigDecimal::of($user->mine_power)->plus($log->power);
//                                $wx_mine_power =  (BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN))->plus($log->user->old_mine_power)->minus($log->user->mine_power);

//                                $new_power = BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN)->plus(BigDecimal::of($user->old_mine_power));

                                if ($user->is_valid == 0 ) {
                                    if ($new_power->isGreaterThanOrEqualTo(240)) {
                                        $user->is_valid = 1;
                                        $is_upgrade_vip = true;
                                    }
                                }
                                $user->wx_mine_power = BigDecimal::of($user->wx_mine_power)->plus($log->power);
                                $user->mine_power = $new_power;
                                $user->burn_power = BigDecimal::of($user->burn_power)->plus($log->power);

                                if ($user->share_status == 0 ) {
                                    if ( $user->burn_power->isGreaterThanOrEqualTo(6000)) {
                                        $user->share_status = 1;
                                    }
                                }

                                $user->save();
//                            }
                            }

                            Db::commit();

                            $queueService = $this->container->get(QueueService::class);
                            $queueService->pushUpdatePower([
                                'user_id'        => $user->id,
                                'is_upgrade_vip' => $is_upgrade_vip
                            ]);
                        } catch (\Exception $e) {
                            Db::rollBack();
                            \App\Utils\Log::get()->error(sprintf("【检查进程】更新算力失败:%s",  $e->getMessage));
                            throw new \Exception("【检查进程】更新算力失败：" . $e->getMessage());
                        }
                    }

                }


        });
    }
}
