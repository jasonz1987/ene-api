<?php

declare(strict_types=1);

namespace App\Job;

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
use Hyperf\Utils\ApplicationContext;

class UpdateTeamLevelJob extends Job
{
    public $params;

    /**
     * 任务执行失败后的重试次数，即最大执行次数为 $maxAttempts+1 次
     *
     * @var int
     */
    protected $maxAttempts = 2;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        if (!$this->params) {
            return;
        }

        $userService = ApplicationContext::getContainer()->get(UserService::class);

        $user = User::find($this->params);

        if (!$user) {
            Log::get()->error(sprintf('【%s】:用户不存在' , $this->params));
        }

        $parents = $user->parents()->with('child')->get();

        // 获取用户的父级树
        $tree = $userService->getParentTree($user, $parents);

        if (!$tree) {
            Log::get()->error(sprintf('【%s】:无父节点' , $this->params));
        }

        $min_level = 0;

        Db::beginTransaction();

        try {
            foreach ($tree as $k=>$v){
                // 判断用户等级

                if ($v->user->vip_level == 0) {
                    if ($userService->getTeamNum($v->user) >= 299) {
                        $min_level = 1;
                        $v->user->vip_level = 1;
                        $v->user->save();
                    }
                } else {
                    if ($v->user->vip_level < $min_level) {
                        $v->user->vip_level = $min_level;
                        $v->user->save();
                    }

                    // 判断是否符合升级条件
                    if ($userService->getTeamNewLevel($v->user)) {
                        $v->user->vip_level = $v->user->vip_level + 1;
                        $v->user->save();
                    }

                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            Log::get()->error(sprintf('更新团队等级失败' , $e->getMessage()));
        }

    }
}