<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\BindLog;
use App\Model\DepositLog;
use App\Model\InvitationLog;
use App\Model\User;
use App\Services\QueueService;
use App\Services\ConfigService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
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
class CheckReferrer extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:referrer');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查询绑定');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));

        $abi = '[{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"AddEquipment","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokenId","type":"uint256"}],"name":"AddFarmer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"address","name":"referrer","type":"address"}],"name":"BindReferrer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"power","type":"uint256"}],"name":"UserOut","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"eneReward","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"usdtReward","type":"uint256"}],"name":"UserReward","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[],"name":"FEE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"PERCENT","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"UNIT","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"WITHDRAW_FEE_RATE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addEquipment","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"addFarmer","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_address","type":"address"}],"name":"bindReferrer","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address payable","name":"_addr","type":"address"}],"name":"drawFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"eneToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"eneUsdtLpToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"equipmentContract","outputs":[{"internalType":"contract ENEEquipmentToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"farmerContract","outputs":[{"internalType":"contract ENEFarmerToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"}],"name":"updateWithdrawFeeAddress","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_rate","type":"uint256"}],"name":"updateWithdrawFeeRate","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"usdtToken","outputs":[{"internalType":"contract IERC20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"userAddresses","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"userDirectChildren","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"users","outputs":[{"internalType":"uint256","name":"farmerTokenId","type":"uint256"},{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"address","name":"referrer","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"withdrawFeeAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = env('FARM_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $events = $contract->getEvents();

        $eventName = 'BindReferrer';

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

        \App\Utils\Log::get()->info("【扫描绑定】开始扫描...");

        $tx_ids = BindLog::pluck('tx_id')->toArray();

        $web3->getEth()->getLogs([
            'fromBlock' => '0x' . dechex($latest_block_number - 1000),
            'toBlock' => '0x' . dechex($latest_block_number),
            'topics' => [$eventSignature],
            'address' => $contractAddress
        ],
            function ($err, $result) use (&$eventLogData, &$fromBlock, &$configService, $tx_ids, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                if($err !== null) {
                    \App\Utils\Log::get()->error(sprintf("【扫描绑定】扫描失败:%s", $err->getMessage()));
                    throw new \Exception($err->getMessage());
                }

                \App\Utils\Log::get()->info(sprintf("【扫描绑定】扫描的交易数:%s", count($result)));

                foreach ($result as $object) {

                    \App\Utils\Log::get()->info(sprintf("【扫描绑定】扫描的交易:%s", $object->transactionHash));

                    if (in_array($object->transactionHash, $tx_ids)) {
                        \App\Utils\Log::get()->info(sprintf("【扫描绑定】交易已存在，跳过:%s", $object->transactionHash));
                        continue;
                    }

                    //decode the data from the log into the expected formats, with its corresponding named key
                    $decodedData = array_combine($eventParameterNames, $ethabi->decodeParameters($eventParameterTypes, $object->data));

                    //decode the indexed parameter data
                    for ($i = 0; $i < $numEventIndexedParameterNames; $i++) {
                        //topics[0] is the event signature, so we start from $i + 1 for the indexed parameter data
                        $decodedData[$eventIndexedParameterNames[$i]] = $ethabi->decodeParameters([$eventIndexedParameterTypes[$i]], $object->topics[$i + 1])[0];
                    }

                    Db::beginTransaction();

                    try {


                        $log = new BindLog();
                        $log->tx_id = $object->transactionHash;
                        $log->user =  $decodedData['user'];
                        $log->referrer =  $decodedData['referrer'];
                        $log->block_number = hexdec($object->blockNumber);
                        $log->save();

                        $user = new User();
                        $user->address =  $decodedData['user'];
                        $user->source_address =  $decodedData['referrer'];
                        $user->save();

                        $referrer = User::where('address', '=', $decodedData['referrer'])
                            ->first();

                        if (!$referrer) {
                            $referrer = new User();
                            $referrer->address =  $decodedData['referrer'];
                            $referrer->save();
                        }

                        $this->insertChildren($user, $referrer);

                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollBack();
                        \App\Utils\Log::get()->error(sprintf("更新绑定失败:%s",  $e->getMessage));
                        throw new \Exception("更新绑定失败：" . $e->getMessage());
                    }
                }

//                $configService->setLastBlockNumber($latest_block_number+1);
        });
    }

    protected function insertChildren($user, $source)
    {
        // 获取父级用户所有的父级记录
        $parents = InvitationLog::where('child_id', '=', $source->id)
            ->get();

        $result = [];

        foreach ($parents as $v) {
            $result[] = [
                'user_id'    => $v->user_id,
                'child_id'   => $user->id,
                'level'      => $v->level + 1,
                'parent_id'  => $source->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        $result[] = [
            'user_id'    => $source->id,
            'child_id'   => $user->id,
            'level'      => 1,
            'parent_id'  => $source->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        if ($result) {
            InvitationLog::insert($result);
        }
    }
}
