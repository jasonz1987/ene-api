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
use App\Model\ContractOrder;
use App\Model\ContractPosition;
use App\Model\User;
use App\Services\ContractService;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject;

class ContractController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * @Inject()
     * @var ContractService
     */
    protected $contractService;

    public function indexes()
    {
        $indexes = ContractIndex::where('status', '=', 1)
            ->select(['id', 'title', 'sub_title', 'lever', 'code', 'symbols'])
            ->get()
            ->map(function ($item) {
                $item->id = HashId::encode($item->id);
                $item->quote_change = $this->contractService->getIndexQuoteChange($item->code);
                $symbols = json_decode($item->symbols, true);

                $new_symbols = [];
                foreach ($symbols as $symbol) {
                    $new_symbols[] = strtoupper($symbol['symbol']);
                }
                $item['symbols'] = $new_symbols;
                return $item;
            });

        return [
            'code'    => 200,
            'message' => "",
            'data'    => $indexes
        ];
    }

    public function indexKline(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'id'       => 'required|integer',
                'interval' => 'required | in:1min,5min,15min,30min,60min,4hour,1day,1week,1mon',
            ],
            [
                'id.required'       => 'id is required',
                'interval.required' => 'interval is required',
                'interval.in'       => 'interval error',
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

        $result = [];

        $index = ContractIndex::find($id);

        if ($index) {
            $interval = $this->request->input('interval', '1min');

            $redis = $this->container->get(Redis::class);

            $data = $redis->hGetAll("index.kline." . $index->code . "." . "$interval");
            krsort($data);

            // 截取
            $data = array_slice($data, 0, 300);

            $data = array_reverse($data);

            $data = array_values($data);

            $result = array_map(function ($item) {
                return unserialize($item);
            }, $data);
        }

        return [
            'code'    => 200,
            'message' => "",
            'data'    => $result
        ];
    }

    public function indexMarket(RequestInterface $request)
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
            // Handle exception
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $id = $this->request->input('id');

        $id = HashId::decode($id);

        $index = ContractIndex::where('id', '=', $id)
            ->where('status', '=', 1)
            ->first();

        if (!$index) {
            return [
                'code'    => 500,
                'message' => '指数不存在或已下架',
            ];
        }

        // 获取今日K线
        $today = $this->contractService->getIndexTodayKline($index->code);

        // 获取24小时成交总量
        $trade_volume = ContractOrder::where('status', '=', 1)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
            ->sum('volume');
        // 获取持仓量
        $position_volume = ContractPosition::where('status', '=', 1)
            ->sum('position_volume');

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'last_price'      => $this->contractService->getIndexLastPrice($index->code),
                'open_price'      => (string)$this->contractService->getIndexOpenPrice($index->code),
                'quote_change'    => $this->contractService->getIndexQuoteChange($index->code),
                'today_high'      => $today ? strval($today['high']) : null,
                'today_low'       => $today ? strval($today['low']) : null,
                'trade_volume'    => $trade_volume * 10,
                'position_volume' => $position_volume * 10,
            ]
        ];
    }

    public function createOrder(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'id'         => 'required|integer',
                'direction'  => 'required | in:buy,sell',
                'price'      => 'required_if:price_type,limit |  min:1 | numeric',
                'volume'     => 'required | integer | min: 1',
                'price_type' => 'required | in:limit,market',
            ],
            [
                'id.required'         => 'id is required',
                'direction.required'  => 'direction is required',
                'direction.in'        => 'direction error',
                'price_type.required' => 'price is required',
                'price_type.in'       => 'price type error',
                'price.required_if'   => 'price is required'
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

        // TODO 判断下单限制
        if (!$this->contractService->addOrderLimit($user->id)) {
            return [
                'code'    => 500,
                'message' => '下单过于频繁',
            ];
        }

        $id = $this->request->input('id');
        $direction = $this->request->input('direction');
        $price_type = $this->request->input('price_type');
        $volume = $this->request->input('volume');
        $price = $this->request->input('price');

        $id = HashId::decode($id);

        $index = ContractIndex::where('id', '=', $id)
            ->where('status', '=', 1)
            ->first();

        if (!$index) {
            return [
                'code'    => 500,
                'message' => '指数不存在或已下架',
            ];
        }

        if ($price_type == 'market') {
            $price = $this->contractService->getIndexLastPrice($index->code);
        }

        // 判断余额
        $amount = BigDecimal::of($volume)->multipliedBy(BigNumber::of($index->size))->multipliedBy($price)->dividedBy($index->lever, 6, RoundingMode::UP);

        // 手续费
        $fee = BigDecimal::of($amount)->multipliedBy($index->fee_rate)->toScale($index->price_decimal, RoundingMode::UP);

        $balance = BigDecimal::of($user->balance);

        if ($amount->plus($fee)->isGreaterThan($balance)) {
            return [
                'code'    => 500,
                'message' => '余额不足',
            ];
        }

        DB::beginTransaction();

        try {
            // 查找是否有对应的仓位
            $position = ContractPosition::where('index_id', '=', $index->id)
                ->where('status', '=', 1)
                ->first();

            // 限价单 立即成交
            if ($price_type == 'market') {
                if ($position) {
                    // 判断 是否为相同方向
                    if ($position->direction != $direction) {
                        return [
                            'code'    => 500,
                            'message' => '暂不支持对冲仓位',
                        ];
                    }
                }
            }

            $order = new ContractOrder();
            $order->index_id = $index->id;
            $order->user_id = $user->id;
            $order->price_type = $price_type;
            $order->volume = $volume;
            $order->direction = $direction;
            $order->lever = $index->lever;
            $order->amount = $amount;
            $order->fee = $fee;
            $order->price = $price;
            $order->save();

            if ($price_type == 'limit') {
                $user->increment('frozen_balance', $amount->plus($fee)->toFloat());
                $user->decrement('balance', $amount->plus($fee)->toFloat());
            } else {
                $this->contractService->updatePosition($order);
                $user->increment('frozen_balance', $amount->toFloat());
                $user->decrement('balance', $amount->plus($fee)->toFloat());
            }

            if ($user->is_open_power == 1) {
                $user->increment('power', $fee->dividedBy(10, 6, RoundingMode::DOWN)->toFloat());
            }

            DB::commit();

            return [
                'code'    => 200,
                'message' => '下单成功'
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'code'    => 500,
                'message' => '下单失败：' . $e->getMessage()
            ];
        }


    }

    public function cancelOrder(RequestInterface $request)
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

        $order = ContractOrder::where('id', '=', $id)
            ->first();

        if (!$order) {
            return [
                'code'    => 500,
                'message' => '订单不存在',
            ];
        }

        if ($order->status != 0) {
            return [
                'code'    => 500,
                'message' => '订单已成交或撤销',
            ];
        }

        Db::beginTransaction();

        try {
            $order->status = 2;
            $order->save();

            // 取消冻结的保证金
            $order->user->decrement('frozen_balance', BigDecimal::of($order->amount)->plus($order->fee)->toFloat());
            $order->user->increment('balance', BigDecimal::of($order->amount)->plus($order->fee)->toFloat());

            Db::commit();

            return [
                'code'    => 200,
                'message' => '撤单成功'
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '撤单失败:' . $e->getMessage()
            ];
        }
    }

    public function closePosition(RequestInterface $request)
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

        $position = ContractPosition::where('id', '=', $id)
            ->where('status', '=', 1)
            ->first();

        if (!$position) {
            return [
                'code'    => 500,
                'message' => '仓位不存在或已平仓',
            ];
        }

        // TODO 判断平仓锁

        Db::beginTransaction();

        try {
            // 计算收益
            $profit = $this->contractService->getUnrealProfit($position);

            $position->profit = $profit;
            $position->status = 0;
            $position->liquidate_type = 0;
            $position->save();

            // 取消冻结的保证金
            if ($profit->isGreaterThan(0)) {
                $total_pledge = User::sum('market_pledge');

                if (BigDecimal::of($total_pledge)->isGreaterThan($profit)) {
                    $position->user->decrement('frozen_balance', BigDecimal::of($position->position_amount)->toFloat());
                    $position->user->increment('balance', $profit->toFloat());
                } else {
                    $position->reward_status = 0;
                    $position->save();
                }
            } else {
                if ($profit->abs()->isLessThan(BigDecimal::of($position->position_amount))) {
                    $position->user->decrement('frozen_balance', BigDecimal::of($position->position_amount)->minus($profit->abs())->toFloat());
                    $position->user->increment('balance', BigDecimal::of($position->position_amount)->minus($profit->abs())->toFloat());
                }
            }

            Db::commit();

            return [
                'code'    => 200,
                'message' => '平仓成功'
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '平仓失败:' . $e->getMessage()
            ];
        }
    }

    public function positions(RequestInterface $request)
    {
        $user = Context::get('user');

        $positions = ContractPosition::with('index')
            ->where('user_id', '=', $user->id)
            ->where('status', '=', 1)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatPositions($positions),
            'page'    => $this->getPage($positions)
        ];

    }

    protected function formatPositions($positions)
    {
        $result = [];

        foreach ($positions as $position) {

            $profit = $this->contractService->getUnrealProfit($position);
            $rate = $profit->dividedBy($position->position_amount, 4, RoundingMode::UP);

            $result[] = [
                'id'                => HashId::encode($position->id),
                'direction'         => $position->direction,
                'position_volume'   => $position->position_volume,
                'open_price'        => BigDecimal::of($position->open_price)->toScale($position->index->price_decimal),
                'liquidation_price' => $this->contractService->getLiquidationPrice($position),
                'position_amount'   => BigDecimal::of($position->position_amount)->toScale(6),
                'profit_unreal'     => $profit,
                'profit_rate'       => $rate,
                'lever'             => $position->lever,
                'index'             => [
                    'id'        => HashId::encode($position->index_id),
                    'title'     => $position->index->title,
                    'sub_title' => $position->index->sub_title,
                ]
            ];
        }

        return $result;
    }

    public function orders(RequestInterface $request)
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

        $orders = ContractOrder::with('index')
            ->where('user_id', '=', $user->id)
            ->where('status', '=', 0)
            ->orderBy('id', 'desc')
            ->paginate((int)$request->input('per_page', 10));

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatOrders($orders),
            'page'    => $this->getPage($orders)
        ];
    }

    public function historyOrders(RequestInterface $request)
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

        $orders = ContractOrder::with('index')
            ->where('user_id', '=', $user->id)
            ->where('status', '>', 0)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return [
            'code'    => 200,
            'message' => '',
            'data'    => $this->formatOrders($orders),
            'page'    => $this->getPage($orders)
        ];
    }

    protected function formatOrders($orders)
    {
        $result = [];

        foreach ($orders as $order) {
            $result[] = [
                'id'           => HashId::encode($order->id),
                'direction'    => $order->direction,
                'price'        => MyNumber::formatSoke($order->price),
                'volume'       => $order->volume,
                'trade_volume' => $order->volume,
                'price_type'   => $order->price_type,
//                'amount'       => BigDecimal::of($order->amount)->toScale(6),
//                'trade_amount' => BigDecimal::of($order->trade_amount)->toScale(6),
                'fee'          => MyNumber::formatSoke($order->fee),
                'created_at'   => Carbon::parse($order->created_at)->toDateTimeString(),
                'lever'        => $order->lever,
                'index'        => [
                    'id'        => HashId::encode($order->index_id),
                    'title'     => $order->index->title,
                    'sub_title' => $order->index->sub_title,
                ]
            ];
        }

        return $result;
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

        $logs = ContractPosition::where('user_id', '=', $user->id)
            ->where('status', '=', 0)
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
                'reward'     => MyNumber::formatSoke($log->profit),
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
