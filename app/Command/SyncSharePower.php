<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\InvitationLog;
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
class SyncSharePower extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('sync:share-power');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('同步分享算力');
    }

    public function handle()
    {
        $userService = make(UserService::class);

        $users = User::where('is_valid', '=', 1)
            ->get();

        foreach ($users as $user) {
            $collection = $user->children()->with('child')->get();

            $user->new_share_power = $userService->getSharePower($user, $collection);
            if ($user->vip_level > 0) {
                $user->new_team_power = $userService->getTeamPower($user, $collection);
            }
            $user->save();
            usleep(100);
        }

    }

//    protected function getTeamPower($user, $collection = null) {
//        $userService = make(UserService::class);
//
//        $total_power = BigDecimal::zero();
//
//        if ($user->vip_level == 0 || $user->is_valid == 0) {
//            return $total_power;
//        }
//
//        if (!$collection) {
//            $collection = $user->children()->with('child')->get();
//        }
//
//        $children = $collection->where('level', '=', 1)->all();
//        $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();
//
//        $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
//            ->selectRaw('SUM(users.mine_power) as team_power, user_id')
//            ->whereIn('user_id', $uids)
//            ->where('is_valid', '=', 1)
//            ->groupBy('user_id')
//            ->get();
//
//        foreach ($children as $child) {
//            $tree = $trees->where('user_id', '=', $child->child_id)->first();
//
//            if ($tree) {
//                $power = BigDecimal::of($tree->team_power)->plus($child->child->mine_power);
//            } else {
//                $power = BigDecimal::of($child->child->mine_power);
//            }
//
//            $rate = 0;
//
//            // 平级
//            if ($child->child->vip_level == $user->vip_level) {
//                $rate = 0.01;
//            } else if ($child->child->vip_level <  $user->vip_level) {
//                $rate1 = $userService->getTeamLevelRate($user->vip_level);
//                $rate2 = $userService->getTeamLevelRate($child->child->vip_level);
//                $rate = $rate1 - $rate2;
//            }
//
//            if ($rate > 0) {
//                $real_power = $power->multipliedBy($rate);
//                // 计算总算李
//                $total_power = $total_power->plus($real_power);
//            }
//        }
//
//        return $total_power;
//    }


//    protected function getTeamPower4($user, $collection = null) {
//        $userService = make(UserService::class);
//        $startTime = microtime(true);
//
//        $total_power = BigDecimal::zero();
//
//        if ($user->vip_level == 0 || $user->is_valid == 0) {
//            return $total_power;
//        }
//
//        if (!$collection) {
//            $collection = $user->children()->with('child')->orderBy('level', 'asc')->get();
//        }
//
//        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));
//
//        $excludes = [];
//        $rewards = [];
//        $reward_children = [];
//
//        foreach ($collection as $item) {
//
//            // 父级是否在排除名单
//            if (in_array($item->parent_id, $excludes)) {
//                continue;
//            }
//
//            if ($item->level == 1) {
//                if ($item->child->vip_level <= $user->vip_level) {
//                    $rewards[$item->child_id] = [
//                        'child' =>  $item,
//                        'diff'  =>  BigDecimal::zero()
//                    ];
//
//                    $reward_children[$item->child_id] = [
//                        'root'   =>  $item->child_id,
//                        'child'   =>  $item
//                    ];
//                }
//
//                continue;
//            }
//
//            if (isset($reward_children[$item->parent_id])) {
//                $root_id = $reward_children[$item->parent_id]['root'];
//
//                $reward_children[$item->child_id] = [
//                    'root'  =>  $reward_children[$item->parent_id]['root']
//                ];
//
//                if ($item->child->vip_level > $rewards[$root_id]['child']->child->vip_level) {
//
//                    // 减去自身团队算力
//                    $rewards[$root_id]['diff'] = BigDecimal::of($rewards[$root_id]['diff'])->plus($item->child->mine_power)->plus($item->child->team_mine_power);
//
//                    if ($item->child->vip_level <= $user->vip_level) {
//                        $rewards[$item->child_id] = [
//                            'child' =>  $item,
//                            'diff'  =>  BigDecimal::zero()
//                        ];
//                        $reward_children[$item->child_id] = [
//                            'root'  =>  $item->child_id
//                        ];
//                    } else {
//                        $excludes[] = $item->child_id;
//                    }
//
//                    if ($rewards[$root_id]['child']->child->vip_level == 0) {
//                        if (BigDecimal::of($rewards[$root_id]['diff'])->isGreaterThanOrEqualTo($rewards[$root_id]['child']->child->team_mine_power)) {
//                            unset($rewards[$root_id]);
//                        }
//                    }
//                }
//            }
//        }
//
////        var_dump($rewards);
//
//        foreach ($rewards as $v) {
////            var_dump($v['child']->child_id);
////            var_dump((string)$v['diff']);
//            $rate = 0;
//
//            if ($v['child']->child->vip_level == $user->vip_leve1l) {
//                $rate = 0.01;
//            } else if ($v['child']->child->vip_level <  $user->vip_level) {
//                $rate1 = $userService->getTeamLevelRate($user->vip_level);
//                $rate2 = $userService->getTeamLevelRate($v['child']->child->vip_level);
//                $rate = $rate1 - $rate2;
//            }
//
//            if ($rate > 0) {
//                $team_power = BigDecimal::of($v['child']->child->mine_power)->plus($v['child']->child->team_mine_power)->minus($v['diff']);
//                $real_power = $team_power->multipliedBy($rate);
//                $total_power = $total_power->plus($real_power);
//            }
//        }
//
//        var_dump((string)$total_power);
//
//        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));
//
//        return $total_power;
//    }



}
