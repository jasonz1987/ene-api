<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Api;


use App\Controller\AbstractController;
use App\Helpers\MyConfig;
use App\Model\BurnLog;
use App\Model\PowerRewardLog;
use App\Model\ProfitLog;
use App\Model\User;
use App\Services\ConfigService;
use App\Services\EthService;
use App\Services\QueueService;
use App\Services\UserService;
use App\Services\UserService2;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class BurnController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        $ethService = make(EthService::class);

        // 获取挖矿算力
        $mine_power = $user->mine_power;

        $global_power = $users = User::where('is_valid', '=', 1)
            ->where('mine_power', '>', 0)
            ->sum('mine_power');

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'global_power' => MyNumber::formatPower($global_power),
                'my_power'     => MyNumber::formatPower($mine_power),
                'balance'      => MyNumber::formatCpu($user->wx_balance),
                'burn_profit'  => MyNumber::formatCpu($user->burn_profit),
                'fee_address'  => env('WX_REWARD_ADDRESS'),
                'fee_rate'     => 2,
                'gas_limit'    => 100000
            ]
        ];
    }


    public function share(RequestInterface $request)
    {
        $user = Context::get('user');

        $userService = ApplicationContext::getContainer()->get(UserService2::class);

        // 获取全网总算力
        $collection = $user->children()->with('child')->get();

        // 获取分享算力
//        $share_power = $userService->getSharePower($user, $collection);
//        // 获取团队算力
//        $team_power = $userService->getTeamPower($user, $collection);

        $share_power = $user->new_share_power;
        $team_power = $user->new_team_power;

        // 累计个人算力
        $my_power = BigDecimal::of($share_power)->plus($team_power);

        // 获取直邀用户数量

        $direct_num = $userService->getDirectChildrenNum($collection);

        // 获取团队有效用户数量
        if ($user->vip_level == 0) {
            $team_num = $userService->getTeamNum($user, $collection);
        } else {
            $team_num = $userService->getTeamNodes($user, $collection);
        }

        $global_power = $this->getGlobalPower();

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'global_power' => MyNumber::formatPower($global_power),
                'my_power'     => MyNumber::formatPower($my_power),
                'team_power'   => MyNumber::formatPower($team_power),
                'share_power'  => MyNumber::formatPower($share_power),
                'share_status' => $user->share_status,
                'share_profit' => MyNumber::formatCpu($user->share_profit),
                'top_profit'   => MyNumber::formatCpu($user->top_profit),
                'balance'      => MyNumber::formatCpu($user->wx_balance),
                'vip_level'    => $user->vip_level,
                'direct_num'   => $direct_num,
                'team_num'     => $team_num,
                'fee_address'  => env('WX_REWARD_ADDRESS'),
                'fee_rate'     => 2,
                'gas_limit'    => 100000,
                'top'          => $this->getTop()
            ]
        ];
    }


    public function getGlobalPower()
    {
        $redis = ApplicationContext::getContainer()->get(Redis::class);

//        if ($redis->get("global_power")) {
//            return $redis->get("global_power");
//        }

        // 获取所有的用户
        $users = User::where('is_valid', '=', 1)
            ->where('mine_power', '>', 0)
            ->get();

        $global_power = BigDecimal::zero();

        foreach ($users as $user) {
            if ($user->share_status == 1) {
                $total_power = BigDecimal::of($user->new_share_power)->plus($user->new_team_power);
                $global_power = $global_power->plus($total_power);
            }
        }

//        $redis->set("global_power", (string)$global_power, 300);

        return $global_power;
    }

    public function getTop()
    {
        $time = Carbon::parse(date('Y-m-d').' 21:00:00');

        // 小于9点
        if (Carbon::now()->lt($time)) {
            $time_copy = clone($time);
            $period = [$time_copy->subDay(), $time];
        } else {
            $time_copy = clone($time);
            $period = [$time, $time_copy->addDay()];
        }

        // 获取今日推荐的所有销毁订单
        $logs = BurnLog::whereBetween('created_at', $period)
            ->with('user.parent')
            ->get();

        $users = [];

        foreach ($logs as $log) {
            // 获取父级
            if ($log->user->parent) {
                if ($log->user->parent->share_status == 0) {
                    continue;
                }

                if (isset($users[$log->user->parent->address])) {
                    $users[$log->user->parent->address] = BigDecimal::of($users[$log->user->parent->address])->plus($log->power);
                } else {
                    $users[$log->user->parent->address] = $log->power;
                }
            }
        }

        arsort($users);

        $result = [];

        foreach ($users as $k => $v) {
            $result[] = [
                'address' => substr($k, 0, 6) . '...' . substr($k, -4),
                'power'   => MyNumber::formatPower($v)
            ];
        }

        return $result;

    }

}
