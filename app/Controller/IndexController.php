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
use App\Model\FundProduct;
use App\Model\MarketPledgeLog;
use App\Model\RechargeLog;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use App\Services\ContractService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class IndexController extends AbstractController
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

    public function index()
    {
        $user = Context::get('user');

        // 指数
        $indexes = ContractIndex::orderBy('id')
            ->select('id', 'title', 'sub_title', 'code')
            ->get()
            ->map(function ($item) {
                $item->id = HashId::encode($item->id);
                $item->quote_change = $this->contractService->getIndexQuoteChange($item->code);
                return $item;
            })
            ->toArray();

        // 基金
        $funds = FundProduct::orderBy('id')
            ->select('id', 'title', 'periods')
            ->get()
            ->map(function ($item) {
                $periods = json_decode($item->periods, true);

                if ($periods) {
                    $item['profit'] = $periods[0]['profit'];
                }

                unset($item['periods']);

                $item->id = HashId::encode($item->id);

                return $item;
            })
            ->toArray();

        return [
            'status_code' => 200,
            'message'     => "",
            'data'        => [
                'global' => [
                    'market_pool'    => 0,
                    'incentive_pool' => 0,
                ],
                'my'     => [
                    'market_pledge' => 0,
                    'balance'       => 0,
                    'address'       => $user->balnace,
                    'power'         => $user->power,
                    'market_income' => 0,
                    'market_loss'   => 0,
                    'power_income'  => 0,
                    'fund_income'   => 0
                ],
                'power'  => [
                    'power_pool'    => 0,
                    'power_rate'    => 10,
                    'is_open_power' => $user->is_open_power
                ],
                'index'  => $indexes,
                'fund'   => $funds
            ]
        ];
    }

    public function recharge(RequestInterface $request)
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
        $order = RechargeLog::where('no', '=', $no)
            ->first();

        if (!$order) {
            return [
                'code'    => 500,
                'message' => '订单不存在',
            ];
        }

        if ($order->status > 0) {
            return [
                'code'    => 500,
                'message' => '订单已处理',
            ];
        }

        // 校验交易ID
        try {
            $order->tx_id = $id;
            $order->save();

            return [
                'code'    => 200,
                'message' => '提交成功',
            ];

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => '订单提交失败:' . $e->getMessage(),
            ];
        }
    }

    public function rechargeOrder(RequestInterface $request)
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

        $hash = md5($user->id . http_build_query($request->all()) . time());

        try {
            $log = new RechargeLog();
            $log->user_id = $user->id;
            $log->amount = $request->input('amount');
            $log->hash = $hash;
            $log->no = 'ZHCZ' . time() . mt_rand(10000, 99999);
            $log->from = $user->address;
            $log->to = '0x111';
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

    public function withdraw(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'amount' => 'required',
            ],
            [
                'amount' => 'amount is required',
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

            return [
                'code'    => 200,
                'message' => '提交成功',
            ];

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => '订单提交失败:' . $e->getMessage(),
            ];
        }
    }

}
