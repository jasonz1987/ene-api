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
class SyncVipLevel extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('sync:vip-level');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('同步节点等级');
    }

    public function handle()
    {
        $userService = make(UserService::class);

//        $users = User::where('is_valid', '=', 1)
//            ->where('vip_level1', '=', 0)
//            ->get();
//
//
//        foreach ($users as $user) {
//            if ($user->team_valid_num >= 30) {
//                $user->vip_level1 = 1;
//                $user->save();
//            }
//        }

        $users = User::where('is_valid', '=', 1)
            ->where('vip_level1', '=', 4)
            ->get();


        foreach ($users as $user) {
            // 获取 部门有效节点
            if($this->isNewLevel($user)) {
                $user->vip_level1 += 1;
                $user->save();
            }
        }
    }

    protected function isNewLevel($user) {

        if( $user->vip_level1 == 5) {
            return false;
        }

        if ($user->vip_level1 == 0) {
            if ($user->team_valid_num >= 30) {
                return true;
            }
        } else {
            $collection = $user->children()->with('child')->get();
            $uids = $collection->where('level', '=', 1)->pluck('child_id')->toArray();

            // 获取
            $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                ->selectRaw('count(1) as count, user_id')
                ->whereIn('user_id', $uids)
                ->where('vip_level1', '=', $user->vip_level1)
                ->groupBy('user_id')
                ->get();

            $count = 0;

            foreach ($trees as $tree) {
                if ($tree->count > 0) {
                    $count++;
                }
                if ($count >= 3) {
                    break;
                }
            }

            if ($count >= 3) {
                return true;
            }
        }

        return false;
    }



}
