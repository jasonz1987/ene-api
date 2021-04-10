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
use App\Model\FundRewardLog;
use App\Model\MarketPledgeLog;
use App\Model\MarketRewardLog;
use App\Model\PowerRewardLog;
use App\Model\IndexAccountLog;
use App\Model\User;
use App\Services\ConfigService;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
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

    /**
     * @Inject
     * @var ConfigService
     */
    private $configService;

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

        // 获取总质押
        $total_pledge = User::sum('market_pledge');

        // 获取做市收益
        $market_income = MarketRewardLog::where('user_id', '=', $user->id)
            ->where('reward', '>', 0)
            ->sum('reward');

        // 获取做市亏损
        $market_loss = MarketRewardLog::where('user_id', '=', $user->id)
            ->where('reward', '<', 0)
            ->sum('reward');

        // 获取基金亏损
        $power_reward = PowerRewardLog::where('user_id', '=', $user->id)
            ->sum('reward');

        // 获取基金亏损
        $fund_reward = FundRewardLog::where('user_id', '=', $user->id)
            ->sum('reward');

        // 获取总算力
        $total_power = User::sum('power');

        return [
            'status_code' => 200,
            'message'     => "",
            'data'        => [
                'global' => [
                    'market_pool'            => MyNumber::formatSoke($total_pledge),
                    'incentive_pool_address' => $this->configService->getKey('INCENTIVE_POOL_ADDRESS'),
                    'defi_pool_address'      => $this->configService->getKey('DEFI_POOL_ADDRESS')
                ],
                'my'     => [
                    'market_pledge' => MyNumber::formatSoke($user->market_pledge),
                    'balance'       => MyNumber::formatSoke($user->balance),
                    'address'       => $user->address,
                    'power'         => MyNumber::formatSoke($user->power),
                    'market_income' => MyNumber::formatSoke($market_income),
                    'market_loss'   => MyNumber::formatSoke($market_loss),
                    'power_income'  => MyNumber::formatSoke($power_reward),
                    'fund_income'   => MyNumber::formatSoke($fund_reward)
                ],
                'power'  => [
                    'power_pool_address' => $this->configService->getKey('POWER_POOL_ADDRESS'),
                    'power_rate'         => BigDecimal::of($total_power)->isGreaterThan(0) ? MyNumber::formatSoke(BigDecimal::of(1000)->dividedBy($total_power)) : 0,
                    'is_open_power'      => $user->is_open_power
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

        $user = Context::get('user');

        if (!$this->configService->setLimit($user->uid, 'INDEX_RECHARGE_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        $no = $request->input('no');
        $id = $request->input('id');

        // 查找订单
        $order = IndexAccountLog::where('no', '=', $no)
            ->where('type', '=', 1)
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
            $order->status = 1;
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

        $user = Context::get('user');

        if (!$this->configService->setLimit($user->uid, 'INDEX_RECHARGE_ORDER_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        // 构造订单
        Db::beginTransaction();

        $hash = md5($user->id . http_build_query($request->all()) . time());

        try {
            $log = new IndexAccountLog();
            $log->user_id = $user->id;
            $log->amount = $request->input('amount');
            $log->hash = $hash;
            $log->no = 'ZHCZ' . time() . mt_rand(10000, 99999);
            $log->from = $user->address;
            $log->to = $this->configService->getKey('INDEX_RECHARGE_ADDRESS');
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
                'message' => '预下单失败:' . $e->getMessage()
            ];
        }
    }

    public function withdraw(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'amount' => 'required|numeric|min:0',
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

        $user = Context::get('user');

        if (!$this->configService->setLimit($user->uid, 'INDEX_WITHDRAW_LIMIT')) {
            return [
                'code'    => 500,
                'message' => '操作频繁，请稍后再试',
            ];
        }

        $amount = $request->input('amount');

        // 判断余额
        if (BigDecimal::of($user->balance)->isLessThan(BigDecimal::of($amount))) {
            return [
                'code'    => 500,
                'message' => '账户余额不足',
            ];
        }

        // 校验交易ID
        Db::beginTransaction();

        $hash = md5($user->id . http_build_query($request->all()) . time());

        try {
            $user->decrement('balance', $request->input('amount'));

            $log = new IndexAccountLog();
            $log->user_id = $user->id;
            $log->amount = $request->input('amount');
            $log->hash = $hash;
            $log->no = 'ZHTX' . time() . mt_rand(10000, 99999);
            $log->from = $this->configService->getKey('INDEX_WITHDRAW_ADDRESS');
            $log->to = $user->address;
            $log->type = 2;
            $log->save();

            Db::commit();

            return [
                'code'    => 200,
                'message' => '提交成功',
            ];

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => '提交失败:' . $e->getMessage(),
            ];
        }
    }

}
