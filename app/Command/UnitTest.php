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
        $total_power = BigDecimal::zero();

        // 获取全网总算力
        $user = User::find(38);
        $collection = $user->children()->with('child')->get();

        $direct_num = $userService->getDirectChildrenNum($collection);

        if ($direct_num > 0) {

            if ($direct_num > 10) {
                $direct_num = 10;
            }

            var_dump($direct_num);

            $trees = $userService->getTrees($collection, $user->id, true);

            // 获取奖励的代数和比例
            $levels = $userService->getShareRate($direct_num);

            var_dump($levels);

            $users = [];

            foreach ($trees as $kk=>$tree) {

                var_dump("树：".$kk);

                // 根据推荐数量获取对应的层级
                $new_tree = array_slice($tree, 0, $direct_num);

                foreach($new_tree as $k=>$v) {
                    var_dump("层级：".$k);
                    var_dump("用户ID:" .$v->id);

                    if (!isset($users[$v->id])) {
                        $rate = $levels[$k];
                        // 烧伤
                        if (BigDecimal::of($user->mine_power)->isLessThan($v->mine_power)) {
                            $power = BigDecimal::of($user->mine_power);
                        } else {
                            $power = BigDecimal::of($v->mine_power);
                        }

                        $power_add = $power->multipliedBy($rate);
                        var_dump("算力:".$power_add);
                        $total_power = $total_power->plus($power_add);
                        $users[$v->id] = $v->id;
                    }
                }
            }

            var_dump($total_power);
        }

    }
}
