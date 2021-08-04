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
//        $this->getSharePower2();
        $user = User::find(39);
        $this->getTeamPower2($user);
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

    protected function getSharePower2() {
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

        $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));


        $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
            ->selectRaw('SUM(users.mine_power) as team_power, user_id, mine_power, vip_level')
            ->whereIn('user_id', $uids)
            ->where('is_valid', '=', 1)
            ->groupBy('user_id')
            ->get();

        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        foreach ($trees as $tree) {
            $power = BigDecimal::of($tree->team_power)->plus($tree->mine_power);

            var_dump($tree->user_id);
            var_dump((string)$power);

            $rate = 0;

            // 平级
            if ($tree->vip_level == $user->vip_level) {
                $rate = 0.01;
            } else if ($tree->vip_level < $user->vip_level) {
                $rate1 = $userService->getTeamLevelRate($user->vip_level);
                $rate2 = $userService->getTeamLevelRate($tree->vip_level);
                $rate = $rate1 - $rate2;
            }

            if ($rate > 0) {
                $real_power = $power->multipliedBy($rate);

                var_dump((string)$real_power);
                // 计算总算李
                $total_power = $total_power->plus($real_power);
            }
        }

        var_dump((string)$total_power);


        $this->info(sprintf("耗时：%s ms", (microtime(true) - $startTime) * 1000));

        return $total_power;

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



    protected function getArguments()
    {
        return [
            ['address', InputArgument::OPTIONAL, '地址']
        ];
    }
}
