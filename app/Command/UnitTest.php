<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\BurnLog;
use App\Model\DepositLog;
use App\Model\InvitationLog;
use App\Model\StakeLog;
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
//        $this->getSharePower();
//        $user = User::find($this->input->getArgument('uid'));
////        $this->getSharePower2($user);
////        $this->buildTrees($user);
////        $this->getTeamPower4($user);
////        $this->getTeamNodes2($user);
////        $this->updateParentsLevel($user, 1);
////        $this->updateTokenAddress();
//        $this->getUserLevel($user);
//        $userService = make(UserService::class);
//        var_dump($userService->getTeamNodes($user));

//        $log = new BurnLog();
//        $log->user_id = $user->id;
//        $log->tx_id = mt_rand(1,23243143);
//        $log->block_number = mt_rand(0,1000000);
//        $log->power = mt_rand(1000,5000);
//        $log->burn_cpu = 20;
//        $log->burn_wx = 80;
//        $log->save();



    }

//    protected function updateParentsLevel($user, $level)
//    {
//        $parents = $user->parents()->with('user')->get();
//
//        if ($parents->count() > 0) {
//            foreach ($parents as $parent) {
//
//                if ($parent->user->vip_level > $level) {
//                    continue;
//                }
//
//                if ($parent->user->vip_level >= 5) {
//                    continue;
//                }
//
//                $collection = $parent->user->children()->with('child')->get();
//
//                $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();
//
//                // 获取
//                $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
//                    ->selectRaw('count(1) as count, user_id')
//                    ->whereIn('user_id', $uids)
//                    ->where('vip_level', '=', $level)
//                    ->groupBy('user_id')
//                    ->get();
//
//                $count = 0;
//
//                foreach ($trees as $tree) {
//                    if ($tree->count > 0) {
//                        $count++;
//                    }
//                    if ($count >= 3) {
//                        break;
//                    }
//                }
//
//                var_dump('用户ID：' . $parent->user->id);
//                var_dump('用户VIP等级:' . $parent->user->vip_level);
//                var_dump('节点数：' . $count);
//
//                if ($count >= 3) {
//                    var_dump("需要升级");
//                    $parent->user->vip_level = $level + 1;
////                    $parent->user->save();
//                    $this->updateParentsLevel($parent->user, $parent->user->vip_level);
//                    break;
//                }
//            }
//        }
//    }


    protected function updateTokenAddress() {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('RPC_PROVIDER'), 10)));
        $abi = '[{"inputs":[{"internalType":"address","name":"_cpuTokenAddress","type":"address"},{"internalType":"address","name":"_usdtTokenAddress","type":"address"},{"internalType":"address","name":"_usdtCpuLpAddress","type":"address"},{"internalType":"address","name":"_feeAddress","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Deposit","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"user","type":"address"},{"indexed":true,"internalType":"uint256","name":"pid","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"amount","type":"uint256"}],"name":"Withdraw","type":"event"},{"inputs":[{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"addPool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"deposit","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[],"name":"getBurnAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getDepositCpu","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"getEquivalentUsdt","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getFeeAddress","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"}],"name":"getPoolDeposit","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"getTotalBurn","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"}],"name":"poolInfo","outputs":[{"internalType":"address","name":"tokenAddress","type":"address"},{"internalType":"address","name":"usdtPairAddress","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"","type":"uint256"},{"internalType":"address","name":"","type":"address"}],"name":"poolUserInfo","outputs":[{"internalType":"uint256","name":"amount","type":"uint256"},{"internalType":"uint256","name":"depositTime","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_burnAddress","type":"address"}],"name":"updateBurnAddress","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_feeAddress","type":"address"}],"name":"updateFeeAddress","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_feeRate","type":"uint256"},{"internalType":"uint256","name":"_feeRatePercent","type":"uint256"}],"name":"updateFeeRate","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"address","name":"_tokenAddress","type":"address"},{"internalType":"address","name":"_usdtPairAddress","type":"address"}],"name":"updatePool","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"}],"name":"userInfo","outputs":[{"internalType":"uint256","name":"power","type":"uint256"},{"internalType":"bool","name":"isExist","type":"bool"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_pid","type":"uint256"},{"internalType":"uint256","name":"_amount","type":"uint256"}],"name":"withdraw","outputs":[],"stateMutability":"payable","type":"function"}]';
        $contractAddress = env('CPU_SWAP_CONTRACT_ADDRESS');

        $contract = new Contract($web3->provider, $abi);

        $contract->at($contractAddress)->call('getFeeAddress', [
            'from' => '0x388B239d133b2517e85f3B523517Dc8e65F25146'
        ], function ($err, $result) use (&$new_power) {
            if ($err !== null) {
                throw new \Exception('获取地址失败');
            }
            var_dump($result);
        });

//        $contract->at($contractAddress)->send('updateFeeAddress', '0x450f9f661365BBEf3C49927402D5F4fa9cfb2462', [
//            'from' => '0x388B239d133b2517e85f3B523517Dc8e65F25146',
//            'gas' => '0x200b20'
//        ], function ($err, $result) {
//            if ($err !== null) {
//                throw $err;
//            }
//            if ($result) {
//                echo "\nTransaction has made:) id: " . $result . "\n";
//            }
//        });
    }

    protected function getSharePower() {
        $userService = make(UserService::class);

        $startTime = microtime(true);

        $user = User::find(38);

        $total_power = BigDecimal::zero();

        if ($user->is_valid == 0) {
            return $total_power;
        }

        $collection = $user->children()->with('child')->get();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        // 获取直邀的有效用户
        $direct_num = $userService->getDirectChildrenNum($collection);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        if ($direct_num > 0) {

            if ($direct_num > 10) {
                $direct_num = 10;
            }

            $trees = $userService->getTrees($collection, $user->id, true);

            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

            // 获取奖励的代数和比例
            $levels = $userService->getShareRate($direct_num);

            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

            $users = [];

            foreach ($trees as $tree) {

                // 根据推荐数量获取对应的层级
                $new_tree = array_slice($tree, 0, $direct_num);

                foreach($new_tree as $k=>$v) {

                    if (!isset($users[$v->id])) {
                        $rate = $levels[$k];
                        // 烧伤
                        if (BigDecimal::of($user->mine_power)->isLessThan($v->mine_power)) {
                            $power = BigDecimal::of($user->mine_power);
                        } else {
                            $power = BigDecimal::of($v->mine_power);
                        }

                        $power_add = $power->multipliedBy($rate);
                        $total_power = $total_power->plus($power_add);

                        $users[$v->id] = $v->id;
                    }

                }
            }

            var_dump($total_power);

            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        }
    }

    protected function getSharePower2($user) {
        $userService = make(UserService::class);

        $startTime = microtime(true);

        $total_power = BigDecimal::zero();

        if ($user->is_valid == 0) {
            return $total_power;
        }

        $collection = $user->children()->with('child')->get();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        // 获取直邀的有效用户
        $direct_num = $userService->getDirectChildrenNum($collection);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        if ($direct_num > 0) {

            if ($direct_num > 10) {
                $direct_num = 10;
            }

//            $trees = $userService->getTrees($collection, $user->id, true);
//
//            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

            // 获取奖励的代数和比例
            $levels = $userService->getShareRate($direct_num);

            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

            $users = [];

            $new_collection  = $collection->where('level', '<=', $direct_num)->all();

            foreach ($new_collection as $k=>$v) {

                // 根据推荐数量获取对应的层级
//                $new_tree = array_slice($tree, 0, $direct_num);

                    if (!isset($users[$v->child->id])) {
                        $rate = $levels[$v->level - 1];
                        // 烧伤
                        if (BigDecimal::of($user->mine_power)->isLessThan($v->child->mine_power)) {
                            $power = BigDecimal::of($user->mine_power);
                        } else {
                            $power = BigDecimal::of($v->child->mine_power);
                        }

                        $power_add = $power->multipliedBy($rate);
                        $total_power = $total_power->plus($power_add);

                        $users[$v->child->id] = $v->child->id;
                    }
            }

            var_dump($total_power);

            $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        }
    }

    /**
     * 获取用户的团队算力
     *
     * @param $user
     */
    protected function getTeamPower2($user, $collection = null) {
        $userService = make(UserService::class);
        $startTime = microtime(true);

        $total_power = BigDecimal::zero();

        if ($user->vip_level == 0 || $user->is_valid == 0) {
            return $total_power;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        // 获取直邀用户
        $children = $collection->where('level', '=', 1)->all();
        $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
            ->selectRaw('SUM(users.mine_power) as team_power, user_id')
            ->whereIn('user_id', $uids)
            ->where('is_valid', '=', 1)
            ->groupBy('user_id')
            ->get();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        foreach ($children as $child) {
            $tree = $trees->where('user_id', '=', $child->child_id)->first();

            if ($tree) {
                $power = BigDecimal::of($tree->team_power)->plus($child->child->mine_power);
            } else {
                $power = BigDecimal::of($child->child->mine_power);
            }

            var_dump($child->child->id);
            var_dump((string)$power);

            $rate = 0;

            // 平级
            if ($child->child->vip_level == $user->vip_level) {
                $rate = 0.01;
            } else if ($child->child->vip_level <  $user->vip_level) {
                $rate1 = $userService->getTeamLevelRate($user->vip_level);
                $rate2 = $userService->getTeamLevelRate($child->child->vip_level);
                $rate = $rate1 - $rate2;
            }

            if ($rate > 0) {
                $real_power = $power->multipliedBy($rate);
                var_dump((string)$real_power);
                // 计算总算李
                $total_power = $total_power->plus($real_power);
            }
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        var_dump((string)$total_power);


        return $total_power;

    }


    protected function getTeamPower3($user, $collection = null) {
        $userService = make(UserService::class);
        $startTime = microtime(true);

        $total_power = BigDecimal::zero();

        if ($user->vip_level == 0 || $user->is_valid == 0) {
            return $total_power;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->orderBy('level', 'asc')->get();
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        // 获取直邀用户
        $trees = $this->buildTrees($user, $collection);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        $total_power = BigDecimal::zero();

        $children = [];

        foreach ($trees as $tree) {

            if (count($tree) <= 1) continue;

            $max_level = 0;

            $child = null;

            $diff_power = BigDecimal::zero();

            foreach ($tree as $k=>$v) {
                if ($k == 0) continue;

                if ($v->child->vip_level > 0) {
                    if ($max_level < $v->child->vip_level) {
                        $max_level = $v->child->vip_level;
                    }

                    if ($v->child->vip_level > $user->vip_level) {
                        break;
                    }

                    $child = $v;

                    if ($v->child_id != $child->child_id && $v->child->vip_level >= $child->vip_level) {
                        $diff_power = BigDecimal::of($v->child->mine_power)->plus($v->child->team_mine_power);
                        break;
                    }
                }
            }

            // 全部为0的情况
            if ($max_level == 0) {
                $child = $tree[1];
            }

            if ($child) {

                if (isset($children[$child->child_id])) {
                    if ($diff_power->isGreaterThan(0)) {
                        $children[$child->child_id]['diff'] = $children[$child->child_id]['diff']->plus($diff_power);
                    }
                } else {
                    $children[$child->child_id] = [
                        'child'  => $child,
                        'diff'   => $diff_power
                    ];
                }
            }
        }

        foreach ($children as $k=>$v) {
            if ($v['child']->child->vip_level == $user->vip_level) {
                $rate = 0.01;
            } else if ($v['child']->child->vip_level <  $user->vip_level) {
                $rate1 = $userService->getTeamLevelRate($user->vip_level);
                $rate2 = $userService->getTeamLevelRate($child->child->vip_level);
                $rate = $rate1 - $rate2;
            }

            if ($rate > 0) {
                $team_power = BigDecimal::of($v['child']->child->mine_power)->plus($v['child']->child->team_mine_power)->minus($v['diff']);

                $real_power = $team_power->multipliedBy($rate);
//                    var_dump((string)$real_power);
                // 计算总算李
                $total_power = $total_power->plus($real_power);
            }
        }

        var_dump((string)$total_power);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        return $total_power;
    }

    protected function getTeamPower4($user, $collection = null) {
        $userService = make(UserService::class);
        $startTime = microtime(true);

        $total_power = BigDecimal::zero();

        if ($user->vip_level == 0 || $user->is_valid == 0) {
            return $total_power;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->orderBy('level', 'asc')->get();
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        $excludes = [];
        $rewards = [];
        $reward_children = [];

        foreach ($collection as $item) {

            // 父级是否在排除名单
            if (in_array($item->parent_id, $excludes)) {
                continue;
            }

            if ($item->level == 1) {
                if ($item->child->vip_level <= $user->vip_level) {
                    $rewards[$item->child_id] = [
                        'child' =>  $item,
                        'diff'  =>  BigDecimal::zero()
                    ];

                    $reward_children[$item->child_id] = [
                        'root'   =>  $item->child_id,
                        'child'   =>  $item
                    ];
                }

                continue;
            }

            if (isset($reward_children[$item->parent_id])) {
                $root_id = $reward_children[$item->parent_id]['root'];

                $reward_children[$item->child_id] = [
                    'root'  =>  $reward_children[$item->parent_id]['root']
                ];

                if ($item->child->vip_level > $rewards[$root_id]['child']->child->vip_level) {

                    // 减去自身团队算力
                    $rewards[$root_id]['diff'] = BigDecimal::of($rewards[$root_id]['diff'])->plus($item->child->mine_power)->plus($item->child->team_mine_power);

                    if ($item->child->vip_level <= $user->vip_level) {
                        $rewards[$item->child_id] = [
                            'child' =>  $item,
                            'diff'  =>  BigDecimal::zero()
                        ];
                        $reward_children[$item->child_id] = [
                            'root'  =>  $item->child_id
                        ];
                    } else {
                        $excludes[] = $item->child_id;
                    }

                    if ($rewards[$root_id]['child']->child->vip_level == 0) {
                        if (BigDecimal::of($rewards[$root_id]['diff'])->isGreaterThanOrEqualTo($rewards[$root_id]['child']->child->team_mine_power)) {
                            unset($rewards[$root_id]);
                        }
                    }
                }
            }
        }

//        var_dump($rewards);

        foreach ($rewards as $v) {
            var_dump($v['child']->child_id);
            var_dump((string)$v['diff']);

            if ($v['child']->child->vip_level == $user->vip_level) {
                $rate = 0.01;
            } else if ($v['child']->child->vip_level <  $user->vip_level) {
                $rate1 = $userService->getTeamLevelRate($user->vip_level);
                $rate2 = $userService->getTeamLevelRate($v['child']->child->vip_level);
                $rate = $rate1 - $rate2;
            }

            if ($rate > 0) {
                $team_power = BigDecimal::of($v['child']->child->mine_power)->plus($v['child']->child->team_mine_power)->minus($v['diff']);
                $real_power = $team_power->multipliedBy($rate);
                $total_power = $total_power->plus($real_power);
            }
        }

        var_dump((string)$total_power);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        return $total_power;
    }


    protected function buildTrees($user,$collection = null) {
        $trees[$user->id] = [$user->id];
        $levels[0] = [$user->id];

        $startTime = microtime(true);

        if (!$collection) {
            $collection = $user->children()->with(['child'=> function($query){
                $query->where('is_valid', '=', 1);
            }])->orderBy('level', 'asc')->get();
        }

        foreach ($collection as $k=>$v) {

            if (!$v->child) continue;

            if ($v->level >= 2) {
                if (isset($levels[$v->level-2])) {
                    $users = $levels[$v->level-2];
                    foreach ($users as $user) {
                        unset($trees[$user]);
                    }

                    unset($levels[$v->level-2]);
                }
            }

            $old_path =  $trees[$v->parent_id];
            $old_path[] = $v;

            $trees[$v->child_id] = $old_path;

            if (isset($levels[$v->level-1])) {
                if (!in_array($v->parent_id, $levels[$v->level-1])) {
                    $levels[$v->level-1][] = $v->parent_id;
                }
            } else {
                $levels[$v->level-1][] = $v->parent_id;
            }

//            var_dump($trees);
//            var_dump($levels);

        }

//        var_dump($trees);

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        return $trees;
    }


    protected function getTeamPower($user, $collection = null) {

        $total_power = BigDecimal::zero();

        if ($user->vip_level == 0 || $user->is_valid == 0) {
            return $total_power;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        // 获取该用户下的所有几条线
        $trees = $this->getTrees($collection, $user->id, true);

        foreach ($trees as $tree) {
            $max_level = 0;
            $power = BigDecimal::zero();

            foreach ($tree as $k=>$v) {
                if ($v->vip_level > $max_level) {
                    $max_level = $v->vip_level;
                }

                if (!isset($users[$v->id])) {
                    $power = $power->plus($v->mine_power);
                    $users[$v->id] = $user->id;
                }
            }

            // 平级
            if ($max_level >= $user->vip_level) {
                $rate = 0.01;
            } else {
                $rate = $this->getTeamLevelRate($user->vip_level);
            }

            // 计算总算李
            $total_power = $total_power->plus($power->multipliedBy($rate));
        }

        var_dump($total_power);

        return $total_power;

    }


    protected function updateParentsLevel($user, $level)
    {
        $parents = $user->parents()->with('user')->get();

        Db::beginTransaction();

        if ($parents->count() > 0) {
            foreach ($parents as $parent) {
                $this->info("用户ID:" . $parent->user->id);
                $this->info("用户原等级:". $parent->user->vip_level);

                if ($parent->user->vip_level > $level) {
                    $this->info("无需升级");
                    continue;
                }

                if ($parent->user->vip_level >= 5) {
                    $this->info("最大等级");
                    continue;
                }

                $collection = $parent->user->children()->with('child')->get();

                $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();

                // 获取
                $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                    ->selectRaw('count(1) as count, user_id')
                    ->whereIn('user_id', $uids)
                    ->where('vip_level', '=', $level)
                    ->groupBy('user_id')
                    ->get();

                $count = 0;

                foreach ($trees as $tree) {
                    if ($tree->count > 0) {
                        $count ++;
                    }
                }

                $this->info("符合条件的部门:" . $count);

                if ($count >= 3) {
                    $this->info("升级！！");
                    $parent->user->vip_level = $level + 1;
                    $parent->user->save();
                    $this->updateParentsLevel($parent->user, $parent->user->vip_level);
                    break;
                }
            }
        }
    }


    protected function getUserLevel($user)
    {

                $children = $user->children()->with('child')->where('level', '=', 1)->get();

                $count = 0;
                $uids = [];

                foreach ($children as $child) {
                    if ($child->child->vip_level == $user->vip_level) {
                        $count ++;
                        if ($count >= 3) {
                            $this->info("升级1！");
                            break;
                        } else {
                            continue;
                        }
                    }

                    $uids[] = $child->child_id;
                }


                // 获取
                $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                    ->selectRaw('count(1) as count, user_id')
                    ->whereIn('user_id', $uids)
                    ->where('vip_level', '=', $user->vip_level)
                    ->groupBy('user_id')
                    ->get();

                foreach ($trees as $tree) {
                    if ($tree->count > 0) {
                        $count ++;
                    }
                    if ($count >= 3) {
                        $this->info("升级2！");
                        break;
                    }
                }

                $this->info("符合条件的部门:" . $count);

                if ($count >= 3) {
                    $this->info("升级3！");
                } else {
                    $this->info("未升级！");
                }
    }


    protected function getTeamNodes2($user, $collection = null) {
        $startTime = microtime(true);

        $this->info(sprintf("当前用户等级：%s", $user->vip_level));

        if ($user->vip_level == 0) {
            return 0;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        // 获取直邀用户
        $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
            ->selectRaw('count(1) as count, user_id')
            ->whereIn('user_id', $uids)
            ->where('vip_level', '=', $user->vip_level)
            ->groupBy('user_id')
            ->get();

        $count = 0;

        foreach ($trees as $tree) {
            if ($tree->count > 0) {
                $count ++;
            }
        }

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        $this->info(sprintf("节点：%s", $count));

        return $count;

    }




    protected function getArguments()
    {
        return [
            ['uid', InputArgument::REQUIRED, '用户ID']
        ];
    }
}
