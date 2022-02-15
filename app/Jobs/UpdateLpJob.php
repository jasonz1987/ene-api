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

class UpdateLpJob extends Job
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

            foreach ($parents as $parent) {
                // 获取分享算力
                $share_lp = $this->getShareLp($parent->user);

                $parent->user->share_lp = $share_lp;
                $parent->user->save();
            }

            Db::commit();

            Log::get()->info(sprintf('更新LP成功:%s' , json_encode($this->params)));

        } catch (\Exception $e) {
            Db::rollBack();
            Log::get()->error(sprintf('更新LP失败:%s' , $e->getMessage()));
        }
    }

    protected function getShareLp($user) {
        $total_power = BigDecimal::zero();

        $children = $user->children()->where('level', '<=', 2)->with('child')->orderBy('level', 'asc')->get();

        foreach ($children as $child) {
            if (BigDecimal::of($child->child->total_equipment_power)->isGreaterThan($user->total_equipment_power)) {
                $power = $user->total_equipment_power;
            } else {
                $power = $child->child->total_equipment_power;
            }

            $total_power = $total_power->plus(BigDecimal::of($power)->multipliedBy($this->getShareRate($child->level)));
        }

        return $total_power;
    }

    public function getShareRate($level) {
        $levels = [
            1 =>0.5,
            2 => 0.3
        ];

        return isset($levels[$level]) ? $levels[$level] : 0;
    }

}