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

namespace App\Controller;


use App\Model\PowerRewardLog;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class PowerController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function start(RequestInterface $request)
    {
        $user = Context::get('user');

        if ($user->is_open_power == 1) {
            return [
                'code'    => 500,
                'message' => '已开启，请勿重复操作',
            ];
        }

        $user->is_open_power = 1;
        $user->save();

        return [
            'code'    => 200,
            'message' => '开启成功',
        ];
    }

    public function stop(RequestInterface $request)
    {
        $user = Context::get('user');

        if ($user->is_open_power == 0) {
            return [
                'code'    => 500,
                'message' => '账户未开启算力挖矿',
            ];
        }

        $user->is_open_power = 0;
        $user->save();

        return [
            'code'    => 200,
            'message' => '关闭成功',
        ];
    }

    public function rewardLogs(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'page'     => 'integer | min: 1',
                'per_page' => 'integer | min: 1',
            ],
            [
                'page.integer'     => 'page must be integer',
                'per_page.integer' => 'per_page must be integer'
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $user = Context::get('user');

        $logs = PowerRewardLog::where('user_id', '=', $user->id)
            ->orderBy('id', 'desc')
            ->paginate((int)$request->input('per_page', 10));

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatRewards($logs),
            'page'    => $this->getPage($logs)
        ];
    }

    protected function formatRewards($logs)
    {
        $result = [];

        foreach ($logs as $log) {
            $result[] = [
                'id'         => HashId::encode($log->id),
                'reward'     => MyNumber::formatSoke($log->reward),
                'created_at' => Carbon::parse($log->created_at)->toDateTimeString(),
            ];
        }

        return $result;
    }

    protected function getPage($logs)
    {
        return [
            'total'        => $logs->total(),
            'count'        => $logs->count(),
            'per_page'     => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'total_pages'  => ceil($logs->total() / $logs->perPage()),
        ];
    }
}
