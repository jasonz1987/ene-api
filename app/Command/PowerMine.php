<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\MineLog;
use App\Model\User;
use App\Services\UserService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use function Swoole\Coroutine\batch;

/**
 * @Command
 */
class PowerMine extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('power:mine');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('算力产币');
    }

    public function handle()
    {
        $userService = $this->container->get(UserService::class);
        $redis = $this->container->get(Redis::class);

        // 获取所有的用户
        $users = User::where('mine_power')
            ->get();

        // 计算全网总挖矿算力
        $global_power = BigDecimal::zero();

        $new_users = [];
        $new_logs = [];

        foreach ($users as $user) {
            // 获取分享算力
            $share_power = $userService->getSharePower($user);
            // 获取团队算力
            $team_power = $userService->getTeamPower($user);

            $total_power = BigDecimal::of($user->mine_power)->plus($share_power)->plus($team_power);

            $global_power = $global_power->dividedBy($total_power);

            $new_users[$user->id] = [
                'mine_power'  => $user->mine_power,
                'share_power' => $share_power,
                'team_power'  => $team_power,
                'total_power' => $total_power
            ];
        }

        $redis->set("global_power", (string)$total_power, 300);

        foreach ($new_users as $k => $v) {

            if ($global_power->isGreaterThan(BigDecimal::zero())) {
                $rate = $v['total_power']->dividedBy($global_power)->toScale(18, RoundingMode::DOWN);
            } else {
                $rate = 0;
            }

            $profit = BigDecimal::of(14400 * (10 ** 18))->multipliedBy($rate);

            $new_logs[] = [
                'user_id'      => $k,
                'date'         => date('Y-m-d'),
                'mine_power'   => $v['mine_power'],
                'share_power'  => $v['share_power'],
                'team_power'   => $v['team_power'],
                'total_power'  => $v['total_power'],
                'global_power' => $global_power,
                'profit'       => $profit,
            ];

            $new_users2[] = [
                'id'    =>  $k,
                'profit'    =>  Db::raw('profit +')
            ];
        }

        Db::beginTransaction();

        try {

            if ($new_logs) {
                MineLog::insert($new_logs);
            }

            if ($new_users2) {
                batch()->update(new User(), $new_users2, 'id', true);
            }

            Db::commit();

        } catch (\Exception $e) {
            Db::rollBack();
        }
    }
}
