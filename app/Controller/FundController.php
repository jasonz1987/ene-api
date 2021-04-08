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

class FundController extends AbstractController
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

    public function products()
    {
        $products = FundProduct::where('status', '=', 1)
            ->orderBy('order', 'desc')
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatProducts($products),
        ];
    }

    protected function formatProducts($products)
    {
        $result = [];

        foreach ($products as $product) {
            $result[] = [
                'id'            => HashId::encode($product->id),
                'title'         => $product->title,
                'total_volume'  => $product->total_volume,
                'remain_volume' => $product->remain_volume,
                'periods'       => json_decode($product->periods),
                'unit_price'    => BigDecimal::of($product->unit_price)->toScale(6),
                'created_at'    => Carbon::parse($product->created_at)->toDateTimeString(),
            ];
        }

        return $result;
    }

    public function buy(RequestInterface $request)
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
        $order = FundOrder::where('no', '=', $no)
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

    public function buyOrder(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'id'     => 'required|integer',
                'volume' => 'required|integer|min:1',
                'period' => 'required|integer'
            ],
            [
                'id.required'     => 'id is required',
                'volume.required' => 'volume is required',
                'period.required' => 'period is required'
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $id = $this->request->input('id');
        $volume = $this->request->input('volume');
        $period = $this->request->input('period');

        $id = HashId::decode($id);

        $user = Context::get('user');

        $product = FundProduct::where('status', '=', 1)
            ->where('id', '=', $id)
            ->first();

        if (!$product) {
            return [
                'code'    => 500,
                'message' => '产品不存在或已下架'
            ];
        }

        // 判断period周期是否合法
        if (!$this->fundService->getProductPeriod($product, $period)) {
            return [
                'code'    => 500,
                'message' => '周期不存在'
            ];
        }

        // 判断剩余量
        if ($product->remain_volume < $volume) {
            return [
                'code'    => 500,
                'message' => '剩余份数不足'
            ];
        }

        // 构造订单
        Db::beginTransaction();

        $amount = BigDecimal::of($product->unit_price)->multipliedBy($volume);

        try {
            $order = new FundOrder();
            $order->product_id = $product->id;
            $order->user_id = $user->id;
            $order->unit_price = $product->unit_price;
            $order->period = $period;
            $order->volume = $volume;
            $order->amount = $amount;
            $order->expired_at = Carbon::now()->addMonths($period);
            $order->no = 'JZGM' . time() . mt_rand(10000, 99999);
            $order->save();

            Db::commit();

            return [
                'code'    => 200,
                'message' => '预下单成功',
                'data'    => [
                    'no'     => $order->no,
                    'amount' => $amount->toScale(6)
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

    public function redeem(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'id' => 'required|integer',
            ],
            [
                'id.required' => 'id is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $id = $this->request->input('id');

        $id = HashId::decode($id);

        // TODO 赎回限制

        $order = FundOrder::where('id', '=', $id)
            ->first();

        if (!$order) {
            return [
                'code'    => 500,
                'message' => '订单不存在',
            ];
        }

        if ($order->tx_status != 2) {
            return [
                'code'    => 500,
                'message' => '订单未付款',
            ];
        }

        if ($order->reward_status != 0) {
            return [
                'code'    => 500,
                'message' => '订单已结算',
            ];
        }

        $order->redeemed_at = Carbon::now();
        $order->redeemed_at = Carbon::now();
        $order->save();

        return [
            'code'    => 200,
            'message' => '提交成功',
        ];

    }

    public function buyLogs(RequestInterface $request)
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

        $logs = FundOrder::where('redeem_status', '=', 0)
            ->where('user_id', '=', $user->id)
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatLogs($logs),
            'page'    => $this->getPage($logs)
        ];

    }

    protected function formatLogs($logs)
    {
        $result = [];

        foreach ($logs as $log) {
            $result[] = [
                'id'         => HashId::encode($log->id),
                'unit_price' => MyNumber::formatSoke($log->unit_price),
                'amount'     => MyNumber::formatSoke($log->amount),
                'period'     => $log->period,
                'volume'     => $log->volume,
                'status'     => $log->tx_status,
                'no'         => $log->no,
                'created_at' => Carbon::parse($log->created_at)->toDateTimeString(),
                'product'    => [
                    'id'    => HashId::encode($log->product->id),
                    'title' => $log->product->title,
                ]
            ];
        }

        return $result;
    }

    public function redeemLogs(RequestInterface $request)
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

        $logs = FundOrder::whereNotNull('redeem_at')
            ->where('user_id', '=', $user->id)
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatLogs($logs),
            'page'    => $this->getPage($logs)
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

        $logs = FundRewardLog::where('user_id', '=', $user->id)
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
                'product'    => [
                    'id'    => HashId::encode($log->order->product->id),
                    'title' => $log->order->product->title
                ]
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
