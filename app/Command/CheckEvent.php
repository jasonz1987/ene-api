<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\User;
use App\Services\QueueService;
use App\Services\ConfigService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use http\Exception\RuntimeException;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
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

        $abi='[{"inputs":[{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"addPool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"deposit","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_cpuTokenAddress","type":"address"},{"internalType":"address","name":"_usdtTokenAddress","type":"address"},{"internalType":"address","name":"_usdtCpuLpAddress","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Deposit","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"updatePool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"withdraw","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getDepositCpu","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getEquivalentUsdt","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"}],"name":"getPoolDeposit","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getTotalBurn","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"poolInfo","outputs":[{"internalType":"address","name":"tokenAddress","type":"address"},{"internalType":"address","name":"usdtPairAddress","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"},{"internalType":"address","name":"","type":"address"}],"name":"poolUserInfo","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"depositTime","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"userInfo","outputs":[{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = env('CPU_SWAP_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $events = $contract->getEvents();

        $eventName = 'Deposit';

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

                    $logs = DepositLog::whereIn('tx_id', $hashes)
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
                                $log = new DepositLog();
                                $log->user_id = $user->id;
                                $log->tx_id = $object->transactionHash;
                                $log->pool_id =$decodedData['pid'];
                                $log->amount = BigDecimal::of($decodedData['amount'])->dividedBy(1e18,6, RoundingMode::DOWN);
                                $log->block_number = hexdec($object->blockNumber);
                                $log->save();

                                $new_power = null;

                                $contract->at($contractAddress)->call('userInfo', $user->address, [
                                    'from' => $user->address
                                ], function ($err, $result) use (&$new_power) {
                                    if ($err !== null) {
                                        throw new \Exception('【检查进程】获取用户算力失败');
                                    }

                                    $new_power = $result['power']->toString();
                                });

                                if ($new_power) {
                                    $log->status = 1;
                                    $log->save();
                                    $new_power = BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN)->plus(BigDecimal::of($user->old_mine_power));

                                    if ($user->is_valid == 0 ) {
                                        if ($new_power->isGreaterThan(240)) {
                                            $user->is_valid = 1;
                                            $is_upgrade_vip = true;
                                        }
                                    }

                                    $user->mine_power = $new_power;
                                    $user->save();
                                } else {
                                    throw new \Exception("【检查进程】未获取到算力");
                                }
                            }

                            Db::commit();

                            if ($log->status == 1) {
                                $queueService = $this->container->get(QueueService::class);
                                $queueService->pushUpdatePower([
                                    'user_id'        => $user->id,
                                    'is_upgrade_vip' => $is_upgrade_vip
                                ]);
                            }
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
