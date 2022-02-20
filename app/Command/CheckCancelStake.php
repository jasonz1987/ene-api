<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\BindLog;
use App\Model\DepositLog;
use App\Model\InvitationLog;
use App\Model\StakeLog;
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
class CheckCancelStake extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:cancel-stake');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查询取消质押');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);
        $queueService = $this->container->get(QueueService::class);

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));

        $abi = '[{"inputs":[],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"index","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"period","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"CancelStake","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Reward","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"index","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"period","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Stake","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"}],"name":"Withdraw","type":"event"},{"inputs":[],"name":"FEE","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_index","type":"uint256"}],"name":"cancelStake","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address payable","name":"_addr","type":"address"}],"name":"drawFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"drawLpToken","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"drawToken","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"eneToken","outputs":[{"internalType":"contract IBEP20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"feeRate","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"fundAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getLpValue","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getUserOrders","outputs":[{"components":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"period","type":"uint256"},{"internalType":"uint256","name":"time","type":"uint256"}],"internalType":"struct ENELPFarm.Order[]","name":"","type":"tuple[]"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"lpToken","outputs":[{"internalType":"contract IBEP20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"minStakeAmount","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"orders","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"period","type":"uint256"},{"internalType":"uint256","name":"time","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"periodDays","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"reward","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_period","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"stake","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_fee","type":"uint256"}],"name":"updateFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_rate","type":"uint256"}],"name":"updateFeeRate","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_addr","type":"address"}],"name":"updateFundAddress","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"updateMinStakeAmount","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_index","type":"uint256"},{"internalType":"uint256","name":"_days","type":"uint256"}],"name":"updatePeriodDays","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"usdtToken","outputs":[{"internalType":"contract IBEP20","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"withdraw","outputs":[],"stateMutability":"payable","type":"function"}]';

        $contractAddress = env('STAKE_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $events = $contract->getEvents();

        $eventName = 'CancelStake';

        $latest_block_number = null;

        $web3->getEth()->blockNumber(function ($err, $blockNumber) use(&$latest_block_number) {
            if ($err !== null) {
                $this->error($err);
                return;
            }

            $latest_block_number = (int)($blockNumber->toString());
        });

        if ($latest_block_number == null) {
            \App\Utils\Log::get()->error("【扫描LP取消质押】获取最新区块失败");
            return;
        }

        \App\Utils\Log::get()->info(sprintf("【扫描LP取消质押】最新区块号:%s", $latest_block_number));
        
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

        \App\Utils\Log::get()->info("【扫描LP取消质押】开始扫描...");

        $web3->getEth()->getLogs([
            'fromBlock' => '0x' . dechex($latest_block_number - 4000),
            'toBlock' => '0x' . dechex($latest_block_number),
            'topics' => [$eventSignature],
            'address' => $contractAddress
        ],
            function ($err, $result) use (&$eventLogData, &$fromBlock, $tx_ids, $queueService, &$latest_block_number, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                if($err !== null) {
                    \App\Utils\Log::get()->error(sprintf("【扫描LP取消质押】扫描失败:%s", $err->getMessage()));
                    throw new \Exception($err->getMessage());
                }

                \App\Utils\Log::get()->info(sprintf("【扫描LP取消质押】扫描的交易数:%s", count($result)));

                foreach ($result as $object) {

                    \App\Utils\Log::get()->info(sprintf("【扫描LP取消质押】扫描的交易:%s", $object->transactionHash));

                    if (in_array($object->transactionHash, $tx_ids)) {
                        \App\Utils\Log::get()->info(sprintf("【扫描LP取消质押】交易已存在，跳过:%s", $object->transactionHash));
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

                        $user = User::where('address', '=', $decodedData['user'])
                            ->first();

                        if ($user) {

                            $log = StakeLog::where('user_id', '=', $user->id)
                                ->where('index', '=', $decodedData['index'])
                                ->first();

                            if ($log && $log->status == 0) {
                                $log->status = 1;
                                $log->save();

                                $user->decrement('stake_lp', $log->amount);
                            }

                        }

                        Db::commit();

                        // TODO 更新上级的分享算力和团队算力
                        $queueService->pushUpdateLp([
                            'user_id'        => $user->id,
                        ]);

                    } catch (\Exception $e) {
                        Db::rollBack();
                        \App\Utils\Log::get()->error(sprintf("【扫描LP取消质押】更新算力失败:%s",  $e->getMessage()));
                        throw new \Exception("【扫描LP取消质押】更新算力失败：" . $e->getMessage());
                    }
                }

        });
    }
}
