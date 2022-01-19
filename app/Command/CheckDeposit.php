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
class CheckDeposit extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:deposit');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查询质押');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);

        $cache_block_number = $configService->getLastBlockNumber();

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));

        $abi = '[{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"AddEquipment","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"AddFarmer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"address","name":"referrer","type":"address"}],"name":"BindReferrer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"power","type":"uint256"}],"name":"UserOut","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"eneReward","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"usdtReward","type":"uint256"}],"name":"UserReward","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[],"name":"FEE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"PERCENT","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"UNIT","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"WITHDRAW_FEE_RATE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addEquipment","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addFarmer","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_address","type":"address"}],"name":"bindReferrer","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address payable","name":"_addr","type":"address"}],"name":"drawFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"eneToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"eneUsdtLpToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"equipmentContract","outputs":[{"internalType":"contract ENEEquipmentToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"farmerContract","outputs":[{"internalType":"contract ENEFarmerToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"}],"name":"updateWithdrawFeeAddress","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_rate","type":"uint256"}],"name":"updateWithdrawFeeRate","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"usdtToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"userAddresses","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"userDirectChildren","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"users","outputs":[{"internalType":"uint256","name":"farmerTokenId","type":"uint256"},{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"address","name":"referrer","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"withdrawFeeAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = env('FARM_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $events = $contract->getEvents();

        $eventName = 'AddEquipment';

        $latest_block_number = null;

        $web3->getEth()->blockNumber(function ($err, $blockNumber) use(&$latest_block_number) {
            if ($err !== null) {
                $this->error($err);
                return;
            }

            $latest_block_number = (int)($blockNumber->toString());
        });

        if ($latest_block_number == null) {
            $this->error("获取最新区块失败");
            return;
        }
//
//        $this->info("最新区块");
//        $this->info($latest_block_number);
//
//        if ($cache_block_number == null) {
//            $cache_block_number = $latest_block_number;
//        }
//
//        $this->info("缓存区块");
//        $this->info($cache_block_number);
//
//        \App\Utils\Log::get()->info(sprintf("最新区块:%s", $latest_block_number));
//        \App\Utils\Log::get()->info(sprintf("缓存区块:%s", $cache_block_number));

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
            'fromBlock' => '0x' - $latest_block_number - 5000,
            'toBlock' => '0x' . $latest_block_number,
            'topics' => [$eventSignature],
            'address' => $contractAddress
        ],
            function ($err, $result) use (&$eventLogData, &$fromBlock, &$configService, &$latest_block_number, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                if($err !== null) {
                    \App\Utils\Log::get()->error(sprintf("扫描失败:%s", $err->getMessage()));
                    throw new \Exception($err->getMessage());
                }

                \App\Utils\Log::get()->info(sprintf("扫描的交易数:%s", count($result)));

                foreach ($result as $object) {

                    \App\Utils\Log::get()->info(sprintf("扫描的交易:%s", $object->transactionHash));

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
                                    throw new \Exception('获取用户算力失败');
                                }

                                $new_power = $result['power']->toString();
                            });

                            if ($new_power) {
                                $wx_mine_power =  (BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN))->plus($log->user->old_mine_power)->minus($log->user->mine_power);
                                var_dump((string)$wx_mine_power);

                                $log->status = 1;
                                if ($configService->isWxMineStart()) {
                                    $log->power = $wx_mine_power;
                                }
                                $log->save();

                                $new_power = BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN)->plus(BigDecimal::of($user->old_mine_power));

                                if ($user->is_valid == 0 ) {
                                    if ($new_power->isGreaterThanOrEqualTo(240)) {
                                        $user->is_valid = 1;
                                        $is_upgrade_vip = true;
                                    }
                                }
                                if ($configService->isWxMineStart()) {
                                    $user->wx_mine_power = BigDecimal::of($user->wx_mine_power)->plus($wx_mine_power);
                                }
                                $user->mine_power = $new_power;
                                $user->save();
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
                        \App\Utils\Log::get()->error(sprintf("更新算力失败:%s",  $e->getMessage));
                        throw new \Exception("更新算力失败：" . $e->getMessage());
                    }
                }

//                $configService->setLastBlockNumber($latest_block_number+1);
        });
    }
}
