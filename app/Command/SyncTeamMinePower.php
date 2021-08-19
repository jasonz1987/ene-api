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
class SyncTeamMinePower extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('sync:team-mine-power');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('同步团队挖矿算力');
    }

    public function handle()
    {
        $users = User::where('is_valid', '=', 1)
            ->get();

        foreach ($users as $user) {
            $team_power = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                ->where('user_id', '=', $user->id)
                ->where('is_valid', '=', 1)
                ->sum('mine_power');

            $user->team_mine_power = $team_power;
            $user->save();
            usleep(100);
        }

    }




}
