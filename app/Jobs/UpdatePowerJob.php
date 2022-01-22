<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Model\InvitationLog;
use App\Model\User;
use App\Services\UserService;
use App\Utils\Log;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\AsyncQueue\Job;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use function Swoole\Coroutine\batch;

class UpdatePowerJob extends Job
{
    public $params;

    /**
     * 任务执行失败后的重试次数，即最大执行次数为 $maxAttempts+1 次
     *
     * @var int
     */
    protected $maxAttempts = 0;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        if (!$this->params || !isset($this->params['user_id'])) {
            return;
        }

        $userService = ApplicationContext::getContainer()->get(UserService::class);
        $redis = ApplicationContext::getContainer()->get(Redis::class);

        $user = User::find($this->params['user_id']);

        if (!$user) {
            Log::get()->error(sprintf('【%s】:用户不存在' ,$this->params['user_id']));
            return;
        }

        $parents = $user->parents()->with('user')->get();

        if ($parents->count() == 0) {
            return;
        }

        Db::beginTransaction();

        try {

            $upgrade_users = [];

            $collection = $user->children()->with('child')->orderBy('level', 'asc')->get();
            // 获取分享算力
            $share_power = $userService->getSharePower($user, $collection);
            // 获取团队算力
            $team_info = $userService->getTeamInfo($user, $collection);

            if ($team_info['team_level'] != $user->team_level) {
                $upgrade_users[] = $user;
            }

            $user->share_power = $share_power;
            $user->team_power = $team_info['team_power'];
            $user->small_performance = $team_info['small_performance'];
            $user->team_level = $team_info['team_level'];
            $user->save();

            foreach ($parents as $parent) {
                $collection = $parent->user->children()->with('child')->orderBy('level', 'asc')->get();
                // 获取分享算力
                $share_power = $userService->getSharePower($parent->user, $collection);
                // 获取团队算力
                $team_info = $userService->getTeamInfo($parent->user, $collection);

                if ($team_info['team_level'] != $parent->user->team_level) {
                    $upgrade_users[] = $parent;
                }

                $parent->user->share_power = $share_power;
                $parent->user->team_power = $team_info['team_power'];
                $parent->user->small_performance = $team_info['small_performance'];
                $parent->user->team_level = $team_info['team_level'];

                // 团队业绩
                $parent->user->save();
            }

            foreach ($upgrade_users as $user) {
                $team_power = $userService->getSmallPerformance($user, $collection, true);
                $user->team_power = $team_power;
            }

            Db::commit();

            Log::get()->info(sprintf('更新算力成功:%s' , json_encode($this->params)));

//            $redis->del("global_power");

        } catch (\Exception $e) {
            Db::rollBack();
            Log::get()->error(sprintf('更新算力失败:%s' , $e->getMessage()));
        }
    }

}