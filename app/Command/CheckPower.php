<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\User;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;

/**
 * @Command
 */
class CheckPower extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('check:power');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('更新用户算力');
    }

    public function handle()
    {
        $users = User::where('power', '>', 0)
            ->whereNotNull('power_created_at')
            ->where('power_created_at', '<', Carbon::now()->subDays(1)->toDateTimeString())
            ->update([
                'power' =>  0,
                'power_created_at'  => null
            ]);
    }
}
