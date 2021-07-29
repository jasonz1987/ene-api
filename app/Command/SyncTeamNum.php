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
class SyncTeamNum extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('sync:team-num');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('同步团队有效人数');
    }

    public function handle()
    {
        $userService = make(UserService::class);

        $users = User::where('is_valid', '=', 1)
            ->get();


        foreach ($users as $user) {
            $collection = $user->children()->with('child')->get();

            // 获取团队算力
            $team_num = $userService->getTeamNum($user, $collection);

            $user->team_valid_num = $team_num;
            $user->save();
        }

    }


}
