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

use App\Model\MarketPledgeLog;
use App\Model\MarketRewardLog;
use App\Services\ConfigService;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class MarketController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * @Inject()
     * @var ConfigService
     */
    protected $configService;

    public function pledge(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'id' => 'required',
                'no' => 'required',
            ],
            [
                'id.required' => 'id is required',
                'no.required' => 'no is required',
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

        if (!$this->configService->setLimit($user->id, 'MARKET_PLEDGE_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        $no = $request->input('no');
        $id = $request->input('id');

        // 查找订单
        $order = MarketPledgeLog::where('no', '=', $no)
            ->first();

        if (!$order) {
            return [
                'code'    => 500,
                'message' => '订单不存在',
            ];
        }

        if ($order->tx_status > 0) {
            return [
                'code'    => 500,
                'message' => '订单已处理',
            ];
        }

        // 校验交易ID
        Db::beginTransaction();

        try {
            $order->tx_id = $id;
            $order->status = 1;
            $order->save();

            Db::commit();

            return [
                'code'    => 200,
                'message' => '提交成功'
            ];

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => '提交成功:' . $e->getMessage(),
            ];
        }
    }

    public function pledgeOrder(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'amount' => 'required|numeric|gt:0',
            ],
            [
                'amount.required' => 'amount is required',
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

        if (!$this->configService->setLimit($user->id, 'MARKET_PLEDGE_ORDER_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        $hash = md5($user->id . http_build_query($request->all()) . time());

        // 构造订单
        Db::beginTransaction();

        try {
            $log = new MarketPledgeLog();
            $log->user_id = $user->id;
            $log->amount = $request->input('amount');
            $log->hash = $hash;
            $log->no = 'ZSZY' . time() . mt_rand(10000, 99999);
            $log->from = $user->address;
            $log->to = $this->configService->getKey('MARKET_PLEDGE_RECHARGE_ADDRESS');
            $log->type = 1;
            $log->save();

            Db::commit();

            return [
                'code'    => 200,
                'message' => '下单成功',
                'data'    => [
                    'no'     => $log->no,
                    'from'   => $log->from,
                    'to'     => $log->to,
                    'amount' => MyNumber::formatSoke($log->amount)
                ]
            ];
        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '下单失败:' . $e->getMessage()
            ];
        }
    }

    public function cancelPledge(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'amount' => 'required|numeric|gt:0',
            ],
            [
                'amount.required' => 'volume is required',
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

        if (!$this->configService->setLimit($user->id, 'MARKET_PLEDGE_CANCEL_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        if ($user->market_pledge == 0) {
            return [
                'code'    => 500,
                'message' => '无质押记录',
            ];
        }

        $amount = $request->input('amount');

        if (BigDecimal::of($amount)->isGreaterThan($user->market_pledge)) {
            return [
                'code'    => 500,
                'message' => '余额不足',
            ];
        }

        $hash = md5($user->id . http_build_query($request->all()) . time());

        Db::beginTransaction();

        try {
            $log = new MarketPledgeLog();
            $log->user_id = $user->id;
            $log->amount = $amount;
            $log->hash = $hash;
            $log->no = 'ZSTX' . time() . mt_rand(10000, 99999);
            $log->from = $this->configService->getKey('MARKET_PLEDGE_WITHDRAW_ADDRESS');
            $log->to = $user->address;
            $log->type = 2;
            $log->save();

            $user->decrement('market_pledge', $amount);

            Db::commit();

            return [
                'code'    => 200,
                'message' => '取消成功',
            ];

        } catch (\Exception $e) {

            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '取消失败：' . $e->getMessage(),
            ];
        }

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

        $logs = MarketRewardLog::where('user_id', '=', $user->id)
            ->orderBy('id', 'desc')
            ->paginate((int)$request->input('per_page', 10),['*'], 'page', (int)$request->input('page'));

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
