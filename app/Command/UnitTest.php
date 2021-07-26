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
class UnitTest extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('unit:test');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('单元测试');
    }

    public function handle()
    {
        $userService = make(UserService::class);

//        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));
//
//
//        $abi='[{"inputs":[{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"addPool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"deposit","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_cpuTokenAddress","type":"address"},{"internalType":"address","name":"_usdtTokenAddress","type":"address"},{"internalType":"address","name":"_usdtCpuLpAddress","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Deposit","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"updatePool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"withdraw","outputs":[],"stateMutability":"payable","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getDepositCpu","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getEquivalentUsdt","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"}],"name":"getPoolDeposit","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getTotalBurn","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"poolInfo","outputs":[{"internalType":"address","name":"tokenAddress","type":"address"},{"internalType":"address","name":"usdtPairAddress","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"},{"internalType":"address","name":"","type":"address"}],"name":"poolUserInfo","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"depositTime","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"userInfo","outputs":[{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"}]';
//        $contractAddress = '0x96dC3D92f91b5d12421A6c1f0Ec0F7cef4CdC6E3';
//
//        $contract = new Contract($web3->provider, $abi);
//
//        $address = $this->input->getArgument('address');
//
//        $contract->at($contractAddress)->call('userInfo', $address, [
//            'from' => $address
//        ], function ($err, $result) use (&$new_power) {
//            if ($err !== null) {
//                throw new \Exception('获取用户算力失败');
//            }
//
//            $new_power = $result['power']->toString();
//
//            var_dump((string)BigDecimal::of($new_power)->dividedBy(10**18, 6, RoundingMode::DOWN));
//        });


//        $total_power = BigDecimal::zero();
//        // 获取全网总算力
//        $user = User::find(38);
//        $collection = $user->children()->with('child')->get();
//
//        $direct_num = $userService->getDirectChildrenNum($collection);
//
//        if ($direct_num > 0) {
//
//            if ($direct_num > 10) {
//                $direct_num = 10;
//            }
//
//            var_dump($direct_num);
//
//            $trees = $userService->getTrees($collection, $user->id, true);
//
//            // 获取奖励的代数和比例
//            $levels = $userService->getShareRate($direct_num);
//
//            var_dump($levels);
//
//            $users = [];
//
//            foreach ($trees as $kk=>$tree) {
//
//                var_dump("树：".$kk);
//
//                // 根据推荐数量获取对应的层级
//                $new_tree = array_slice($tree, 0, $direct_num);
//
//                foreach($new_tree as $k=>$v) {
//                    if (!isset($users[$v->id])) {
//                        var_dump("层级：".$k);
//                        var_dump("用户ID:" .$v->id);
//                        $rate = $levels[$k];
//                        // 烧伤
//                        if (BigDecimal::of($user->mine_power)->isLessThan($v->mine_power)) {
//                            $power = BigDecimal::of($user->mine_power);
//                        } else {
//                            $power = BigDecimal::of($v->mine_power);
//                        }
//
//                        $power_add = $power->multipliedBy($rate);
//                        var_dump("算力:".$power_add);
//                        $total_power = $total_power->plus($power_add);
//                        $users[$v->id] = $v->id;
//                    }
//                }
//            }
//
//            var_dump($total_power);
//        }

//        $total_power = BigDecimal::zero();
//        $user = User::find(54);
//        $collection = $user->children()->with('child')->get();
//
//        // 获取该用户下的所有几条线
//        $trees = $userService->getTrees($collection, $user->id, true);
//
//        $users = [];
//
//        foreach ($trees as $tree) {
//            $max_level = 0;
//            $power = BigDecimal::zero();
//
//            foreach ($tree as $k=>$v) {
//
//                var_dump("用户ID：" . $v->id);
//                if ($v->vip_level > $max_level) {
//                    $max_level = $v->vip_level;
//                }
//
//                if (!isset($users[$v->id])) {
//                    $power = $power->plus($v->mine_power);
//                    var_dump("算力：" . $v->mine_power);
//
//                    $users[$v->id] = $v->id;
//                }
//            }
//
//            // 平级
//            if ($max_level >= $user->vip_level) {
//                $rate = 0.01;
//            } else {
//                $rate = $userService->getTeamLevelRate($user->vip_level);
//            }
//
//            var_dump("比率：" . $rate);
//
//            // 计算总算李
//            $total_power = $total_power->plus($power->multipliedBy($rate));
//        }
//
//        var_dump((string)$total_power);

        $user = User::find(456);

        $parents = $userService->getParentTree($user);

        foreach ($parents as $parent) {
            var_dump($parent);
        }

    }

    protected function getArguments()
    {
        return [
            ['address', InputArgument::OPTIONAL, '地址']
        ];
    }
}
