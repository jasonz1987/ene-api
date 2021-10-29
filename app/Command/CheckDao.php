<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\StakeLog;
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
class CheckDao extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:dao');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('获取DAO的质押事件');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));
        $abi = '[{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Stake","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"TakeReward","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[],"name":"draw","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"drawFee","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"drawToken","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"out","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"stake","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"takeReward","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_index","type":"uint256"},{"internalType":"uint256","name":"_period","type":"uint256"},{"internalType":"uint256","name":"_reward_rate","type":"uint256"},{"internalType":"uint256","name":"_period_unit","type":"uint256"}],"name":"updatePeriod","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_time","type":"uint256"}],"name":"updateStartTime","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"withdraw","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_cpuTokenAddress","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"_outIndexes","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"_outOrders","outputs":[{"internalType":"uint256","name":"time","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"cpu","outputs":[{"internalType":"contract CpuToken","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"periods","outputs":[{"internalType":"uint256","name":"period","type":"uint256"},{"internalType":"uint256","name":"rewardRate","type":"uint256"},{"internalType":"uint256","name":"totalStakeAmount","type":"uint256"},{"internalType":"uint256","name":"periodUnit","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"startTime","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"userPeriods","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"users","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"balance","type":"uint256"},{"internalType":"uint256","name":"reward","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"withdrawFee","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = '0x956d4F9b6ffDc613A91A1a244C102c354bC2a85c';

        $contract = new Contract($web3->provider, $abi);
        $events = $contract->getEvents();

        $eventParameterNames = [];
        $eventParameterTypes = [];
        $eventIndexedParameterNames = [];
        $eventIndexedParameterTypes = [];

        $eventName = 'Stake';

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

        $start = 10700506;
        $max = 12179762;

        while($start <= $max) {

            $this->info("区块：". $start);

            $web3->getEth()->getLogs([
                'fromBlock' => '0x' . dechex($start),
                'toBlock' => '0x' . dechex($start+5000),
                'topics' => [$eventSignature],
                'address' => $contractAddress
            ], function ($err, $result) use (&$eventLogData, &$fromBlock, &$configService, &$latest_block_number, $contract, $contractAddress, $ethabi, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames, $numEventIndexedParameterNames) {
                    if($err !== null) {
                        \App\Utils\Log::get()->error(sprintf("扫描失败:%s", $err->getMessage()));
                        throw new \Exception($err->getMessage());
                    }

                    foreach ($result as $object) {

                        \App\Utils\Log::get()->info(sprintf("扫描的交易:%s", $object->transactionHash));

                        //decode the data from the log into the expected formats, with its corresponding named key
                        $decodedData = array_combine($eventParameterNames, $ethabi->decodeParameters($eventParameterTypes, $object->data));

                        //decode the indexed parameter data
                        for ($i = 0; $i < $numEventIndexedParameterNames; $i++) {
                            //topics[0] is the event signature, so we start from $i + 1 for the indexed parameter data
                            $decodedData[$eventIndexedParameterNames[$i]] = $ethabi->decodeParameters([$eventIndexedParameterTypes[$i]], $object->topics[$i + 1])[0];
                        }

                        $contract->at($contractAddress)->call('users', $decodedData['user'], [
                            'from' =>  $decodedData['user']
                        ], function ($err, $result) use ($object,$decodedData) {
                            if ($err !== null) {
                                throw new \Exception('获取用户信息失败');
                            }

                            Db::beginTransaction();

                            try {
                                $log = new StakeLog();
                                $log->address = $decodedData['user'];
                                $log->pid = $decodedData['pid'];
                                $log->amount = BigDecimal::of($decodedData['amount'])->dividedBy(1e18, 6,RoundingMode::DOWN);
                                $log->tx_id = $object->transactionHash;
                                $log->block_number = hexdec($object->blockNumber);
                                $log->user_amount = BigDecimal::of($result['amount'])->dividedBy(1e18, 6,RoundingMode::DOWN);
                                $log->user_reward = BigDecimal::of($result['balance'])->dividedBy(1e18, 6,RoundingMode::DOWN);
                                $log->user_balance = BigDecimal::of($result['reward'])->dividedBy(1e18, 6,RoundingMode::DOWN);
                                $log->save();

                                Db::commit();
                            } catch (\Exception $e) {
                                Db::rollBack();
                                throw new \Exception("更新质押日志失败：" . $e->getMessage());
                            }
                        });
                    }

                });

            $start += 5000;

            }
        }



}
