<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Model\Order;
use App\Model\User;
use App\Service\MarketService;
use App\Service\OrderService;
use App\Service\QueueService;
use App\Service\SymbolService;
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
                    $parent->user->team_valid_num = $parent->user->team_num + 1;
                }

                $parent->user->save();
            }

            Db::commit();

            $redis->del("global_power");

        } catch (\Exception $e) {
            Db::rollBack();
            Log::get()->error(sprintf('更新算力失败' , $e->getMessage()));
        }
    }
}