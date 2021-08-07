<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\User;
use App\Service\QueueService;
use App\Services\ConfigService;
use App\Services\EthService;
use App\Services\UserService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
class SyncPower extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('sync:power');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('同步用户算力');
    }

    public function handle()
    {
        $userService = make(UserService::class);

        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));
        $abi='[{"inputs":[{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"addPool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"deposit","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_cpuTokenAddress","type":"address"},{"internalType":"address","name":"_usdtTokenAddress","type":"address"},{"internalType":"address","name":"_usdtCpuLpAddress","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Deposit","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"updatePool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"withdraw","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getDepositCpu","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getEquivalentUsdt","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"}],"name":"getPoolDeposit","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getTotalBurn","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"poolInfo","outputs":[{"internalType":"address","name":"tokenAddress","type":"address"},{"internalType":"address","name":"usdtPairAddress","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"},{"internalType":"address","name":"","type":"address"}],"name":"poolUserInfo","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"depositTime","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"userInfo","outputs":[{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"}]';
        $contractAddress = env('CPU_SWAP_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $users = User::all();

        foreach ($users as $user) {

            $this->info(sprintf("用户ID: %s", $user->id));
            $this->info(sprintf("原算力: %s", $user->mine_power));

            $contract->at($contractAddress)->call('userInfo', $user->address, [
                'from' => $user->address
            ], function ($err, $result) use (&$new_power) {
                if ($err !== null) {
                    throw new \Exception('获取用户算力失败');
                }

                $new_power = $result['power']->toString();
            });

            if ($new_power) {
                $new_power = BigDecimal::of($new_power)->dividedBy(1e18,6, RoundingMode::DOWN)->plus(BigDecimal::of($user->old_mine_power));
                $this->info(sprintf("新算力: %s", $new_power));

//                $user->mine_power = $new_power;
//                $user->save();
//
//                if ($log->status == 1) {
//                    $queueService = $this->container->get(\App\Services\QueueService::class);
//                    $queueService->pushUpdatePower([
//                        'user_id'        => $log->user->id,
//                        'is_upgrade_vip' => $is_upgrade_vip
//                    ]);
//                }
            } else {
                $this->error("获取算力失败");
            }

//            $collection = $user->children()->with('child')->get();
//
//            // 获取分享算力
//            $share_power = $userService->getSharePower($user, $collection);
//            // 获取团队算力
//            $team_power = $userService->getTeamPower($user, $collection);
//
//            var_dump($share_power);
//            var_dump($team_power);
//
//            $user->share_power = $share_power;
//            $user->team_power = $team_power;
//            $user->save();
        }

    }

//    protected function getArguments()
//    {
//        return [
//            ['uid', InputArgument::REQUIRED, '用户ID'],
//        ];
//    }


}
