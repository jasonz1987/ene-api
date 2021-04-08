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

use App\Model\ContractIndex;
use App\Model\FundOrder;
use App\Model\FundProduct;
use App\Model\FundRewardLog;
use App\Model\MarketPledgeLog;
use App\Model\MarketRewardLog;
use App\Services\FundService;
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
     * @var FundService
     */
    protected $fundService;

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
            $order->tx_status = 1;
            $order->save();

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => '订单提交失败:' . $e->getMessage(),
            ];
        }
    }

    public function pledgeOrder(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'amount' => 'required|integer|min:1',
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

        // TODO 质押限制

        $user = Context::get('user');

        // 构造订单
        Db::beginTransaction();

        try {
            $log = new MarketPledgeLog();
            $log->user_id = $user->id;
            $log->amount = $request->input('amount');
            $log->no = 'ZSZY' . time() . mt_rand(10000, 99999);
            $log->save();

            Db::commit();

            return [
                'code'    => 200,
                'message' => '预下单成功',
                'data'    => [
                    'no'     => $log->no,
                    'amount' => MyNumber::formatSoke($log->amount)
                ]
            ];
        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '预下单失败:' . $e->getMessage()
            ];
        }
    }

    public function cancelPledge(RequestInterface $request)
    {
//        $validator = $this->validationFactory->make(
//            $request->all(),
//            [
//                'id' => 'required|integer',
//            ],
//            [
//                'id.required' => 'id is required',
//            ]
//        );
//
//        if ($validator->fails()) {
//            $errorMessage = $validator->errors()->first();
//            return [
//                'code'    => 400,
//                'message' => $errorMessage,
//            ];
//        }

        $user = Context::get('user');
        // TODO 赎回限制

        if ($user->market_pledge == 0) {
            return [
                'code'    => 500,
                'message' => '没有质押记录',
            ];
        }

        Db::beginTransaction();

        try {
            MarketPledgeLog::where('user_id', '=', $user->uid)
                ->where('tx_status', '=', 2)
                ->whereNotNull('canceled_at')
            - update([
                'canceled_at' => Carbon::now()
            ]);

            $user->market_pledge = 0;
            $user->save();

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

    public function incomeLogs(RequestInterface $request)
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
            ->where('reward', '>', 0)
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatRewards($logs),
            'page'    => $this->getPage($logs)
        ];
    }

    public function lossLogs(RequestInterface $request)
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
            ->where('reward', '<', 0)
            ->orderBy('id', 'desc')
            ->paginate();

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
