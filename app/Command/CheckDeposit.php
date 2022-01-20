<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\BindLog;
use App\Model\DepositLog;
use App\Model\InvitationLog;
use App\Model\User;
use App\Services\QueueService;
use App\Services\ConfigService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
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
        $queueService = $this->container->get(QueueService::class);

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));

        $abi = '[{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addEquipment","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"power","type":"uint256"}],"name":"AddEquipment","type":"event"},{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addFarmer","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"AddFarmer","type":"event"},{"inputs":[{"internalType":"address","name":"_address","type":"address"}],"name":"bindReferrer","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"address","name":"referrer","type":"address"}],"name":"BindReferrer","type":"event"},{"inputs":[{"internalType":"address payable","name":"_addr","type":"address"}],"name":"drawFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_fee","type":"uint256"}],"name":"updateFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"equipmentContract","outputs":[{"internalType":"contract ENEEquipmentToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"farmerContract","outputs":[{"internalType":"contract ENEFarmerToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"FEE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"}],"name":"getUserEquipmentTokenIds","outputs":[{"internalType":"uint256[]","name":"","type":"uint256[]"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"PERCENT","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"userAddresses","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"userDirectChildren","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"users","outputs":[{"internalType":"uint256","name":"farmerTokenId","type":"uint256"},{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"address","name":"referrer","type":"address"}],"stateMutability":"view","type":"function"}]';
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
            \App\Utils\Log::get()->error("【扫描质押】获取最新区块失败");
            return;
        }

        \App\Utils\Log::get()->info(sprintf("【扫描质押】最新区块号:%s", $latest_block_number));

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

        $tx_ids = DepositLog::pluck('tx_id')->toArray();

        \App\Utils\Log::get()->info("【扫描质押】开始扫描...");

        $web3->getEth()->getLogs([
            'fromBlock' => '0x' . dechex($latest_block_number - 1000),
            'toBlock' => '0x' . dechex($latest_block_number),
            'topics' => [$eventSignature],
            'address' => $contractAddress
        ],
            function ($err, $result) use (&$eventLogData, &$fromBlock, $tx_ids, $queueService, &$latest_block_number, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                if($err !== null) {
                    \App\Utils\Log::get()->error(sprintf("【扫描质押】扫描失败:%s", $err->getMessage()));
                    throw new \Exception($err->getMessage());
                }

                \App\Utils\Log::get()->info(sprintf("【扫描质押】扫描的交易数:%s", count($result)));

                foreach ($result as $object) {

                    \App\Utils\Log::get()->info(sprintf("【扫描质押】扫描的交易:%s", $object->transactionHash));

                    if (in_array($object->transactionHash, $tx_ids)) {
                        \App\Utils\Log::get()->info(sprintf("【扫描质押】交易已存在，跳过:%s", $object->transactionHash));
                        continue;
                    }

                    //decode the data from the log into the expected formats, with its corresponding named key
                    $decodedData = array_combine($eventParameterNames, $ethabi->decodeParameters($eventParameterTypes, $object->data));

                    //decode the indexed parameter data
                    for ($i = 0; $i < $numEventIndexedParameterNames; $i++) {
                        //topics[0] is the event signature, so we start from $i + 1 for the indexed parameter data
                        $decodedData[$eventIndexedParameterNames[$i]] = $ethabi->decodeParameters([$eventIndexedParameterTypes[$i]], $object->topics[$i + 1])[0];
                    }

//                    $power = null;

//                    $contract->at($contractAddress)->call('users', $decodedData['user'], [
//                        'from' => $decodedData['user']
//                    ], function ($err, $result) use (&$power) {
//                        if ($err !== null) {
//                            throw new \Exception('获取用户算力失败');
//                        }
//
//                        $power = $result['power']->toString();
//                    });

                    Db::beginTransaction();

                    try {

                        $user = User::where('address', '=', $decodedData['user'])
                            ->first();

                        if ($user) {
                            $log = new DepositLog();
                            $log->user_id = $user->id;
                            $log->tx_id = $object->transactionHash;
                            $log->token_id = $decodedData['tokenId'];
                            $log->block_number = hexdec($object->blockNumber);
                            $log->power = $decodedData['power'];
                            $log->save();

                            $user->increment('equipment_power', (string)$decodedData['power']);
                            $user->increment('total_equipment_power', (string)$decodedData['power']);
                            $user->save();

                            $uids = InvitationLog::where('child_id', '=', $user->id)
                                ->pluck('user_id')->toArray();

                            User::whereIn('id', $uids)
                                ->update([
                                    'team_performance' =>  Db::raw('team_performance +' . (string)$decodedData['power'])
                                ]);
                        }

                        Db::commit();

                        // TODO 更新上级的分享算力和团队算力
                        $queueService->pushUpdatePower([
                            'user_id'        => $user->id,
                            'power' => $decodedData['power']
                        ]);

                    } catch (\Exception $e) {
                        Db::rollBack();
                        \App\Utils\Log::get()->error(sprintf("【扫描质押】更新算力失败:%s",  $e->getMessage()));
                        throw new \Exception("【扫描质押】更新算力失败：" . $e->getMessage());
                    }
                }

        });
    }
}
