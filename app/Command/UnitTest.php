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
//        $this->getSharePower();
        $this->getSharePower2();
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
                        $rate = $levels[$k];
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


    protected function getArguments()
    {
        return [
            ['address', InputArgument::OPTIONAL, '地址']
        ];
    }
}
