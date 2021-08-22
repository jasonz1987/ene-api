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
class GetTeamPower extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('get:team-power');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('获取团队算力');
    }

    public function handle()
    {
        $userService = make(UserService::class);

        $user = User::find($this->input->getArgument('uid'));

        $team_power = $userService->getTeamPower($user);

        var_dump($team_power);

    }

    protected function getArguments()
    {
        return [
            ['uid', InputArgument::REQUIRED, '用户ID']
        ];
    }
}
