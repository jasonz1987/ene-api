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
            Log::get()->error(sprintf('【%s】:用户不存在' , $this->params));
            return;
        }

        $parents = $user->parents()->with('user')->get();

        if ($parents->count() == 0) {
            return;
        }

        Db::beginTransaction();

        try {

            foreach ($parents as $parent) {
                $collection = $parent->user->children()->with('child')->get();
                // 获取分享算力
                $share_power = $userService->getSharePower($parent->user, $collection);
                // 获取团队算力
                $team_power = $userService->getTeamPower($parent->user, $collection);

                $parent->user->share_power = $share_power;
                $parent->user->team_power = $team_power;

                if ($this->params['is_upgrade_vip']) {
                    $parent->user->team_valid_num = $parent->user->team_valid_num + 1;

                    if ($this->isNewLevel($parent->user)) {
                        $parent->user->vip_level = $parent->user->vip_level+1;
                    }
                }

                $parent->user->save();
            }

            Db::commit();

            Log::get()->info(sprintf('更新算力成功:%s' , json_encode($this->params)));

            $redis->del("global_power");

        } catch (\Exception $e) {
            Db::rollBack();
            Log::get()->error(sprintf('更新算力失败:%s' , $e->getMessage()));
        }
    }

    protected function isNewLevel($user) {

        if( $user->vip_level == 5) {
            return false;
        }

        if ($user->vip_level == 0) {
            if ($user->team_valid_num >= 30) {
                return true;
            }
        } else {
            $children = $user->children()->with('child')->where('level', '=', 1)->get();

            $count = 0;
            $uids = [];

            foreach ($children as $child) {
                if ($child->child->vip_level == $user->vip_level) {
                    $count ++;
                    continue;
                }

                if ($count >= 3) {
                    return true;
                }

                $uids[] = $child->child_id;
            }

            if ($uids) {

                // 获取
                $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                    ->selectRaw('count(1) as count, user_id')
                    ->whereIn('user_id', $uids)
                    ->where('vip_level', '=', $user->vip_level)
                    ->groupBy('user_id')
                    ->get();

                foreach ($trees as $tree) {
                    if ($tree->count > 0) {
                        $count++;
                    }
                    if ($count >= 3) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}