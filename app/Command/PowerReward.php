<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\PowerRewardLog;
use App\Model\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;

/**
 * @Command
 */
class PowerReward extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('demo:command');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Hyperf Demo Command');
    }

    public function handle()
    {
        // 获取全网用户
        $users = User::where('power', '>', 0)
            ->where('is_open_power', '=', 1)
            ->get();

        $total_power = BigDecimal::of($users->sum('power'));

        if ($total_power->isGreaterThan(0)) {

            $logs = [];

            foreach ($users as $user) {

                // 计算收益
                $rate = BigDecimal::of($user->power)->dividedBy($total_power, 6, RoundingMode::DOWN);
                $reward = BigDecimal::of(1000)->multipliedBy($rate)->toScale(6, RoundingMode::DOWN);

                $logs [] = [
                    'user_id'     => $user->id,
                    'power'       => $user->power,
                    'total_power' => $total_power,
                    'rate'        => $rate,
                    'reward'      => $reward,
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ];
            }

            if ($logs) {
                PowerRewardLog::insert($logs);
            }
        }


    }
}
