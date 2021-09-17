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

namespace App\Controller\Api;


use _HumbugBoxa9bfddcdef37\Nette\Utils\DateTime;
use App\Controller\AbstractController;
use App\Helpers\MyConfig;
use App\Model\PowerRewardLog;
use App\Model\ProfitLog;
use App\Model\User;
use App\Model\DepositLog;
use App\Model\WxProfitLog;
use App\Services\ConfigService;
use App\Services\EthService;
use App\Services\QueueService;
use App\Services\UserService;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class WxController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        // 累计产出
        $global_power = User::sum('wx_mine_power');

        $today_power = DepositLog::whereDate('created_at', '=', date('Y-m-d'))
            ->where('status', '=', 1)
            ->sum('power');

        $today_power = $today_power ? BigDecimal::of($today_power) : BigDecimal::zero();

        if ($today_power->isLessThan(300000)) {
            $price = 200;
        } elseif ($today_power->isLessThanOrEqualTo(600000)) {
            $price = 375;
        } else {
            $price = 500;
        }

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'global_power' => MyNumber::formatPower($global_power),
                'mine_power'   => MyNumber::formatPower($user->wx_mine_power),
                'today_power'  => MyNumber::formatPower($today_power),
                'time'         => Carbon::now()->diffInSeconds(Carbon::tomorrow()),
                'price'        => MyNumber::formatPower($price),
                'balance'      => MyNumber::formatCpu($user->wx_balance),
                'fee_address'  => env('WX_REWARD_ADDRESS'),
                'fee_rate'     => 2,
                'gas_limit'    => 100000
            ]
        ];
    }

    public function profit(RequestInterface $request)
    {

        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'tx_id' => 'required|regex:/^(0x)?[0-9a-zA-Z]{64}$/',
            ],
            [
                'tx_id.required' => '请提供交易ID',
                'tx_id.regex'    => '交易ID不合法',
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

        if (BigDecimal::of($user->profit)->isLessThan(0.000001)) {
            return [
                'code'    => 500,
                'message' => '暂无可领取收益',
            ];
        }

        $configService = ApplicationContext::getContainer()->get(ConfigService::class);

        if (!$configService->setWithdrawLimit($user->id)) {
            return [
                'code'    => 500,
                'message' => '操作过于频繁，请稍后再试',
            ];
        }

        $ethService = make(EthService::class);

        $gasPrice = $ethService->getGasPrice();

        $fee_tx_id = strtolower($request->input('tx_id'));

        $transaction = $ethService->getTransactionReceipt($fee_tx_id);

        if (!$transaction || hexdec($transaction->status) != 1) {
            return [
                'code'    => 500,
                'message' => '交易失败',
            ];
        }

        if ($transaction->from != $user->address) {
            return [
                'code'    => 500,
                'message' => '交易不合法',
            ];
        }

        if ($transaction->to != strtolower(env('WX_REWARD_ADDRESS'))) {
            return [
                'code'    => 500,
                'message' => '交易不合法',
            ];
        }

//        $fee = BigNumber::of($gasPrice)->multipliedBy($ethService->getGasLimit());
//
//
//        if (BigDecimal::of($transaction->value)->isLessThan($fee)) {
//            return [
//                'code'    => 500,
//                'message' => '手续费不足，请重试',
//            ];
//        }

        $is_exist = WxProfitLog::where('fee_tx_id', $fee_tx_id)
            ->first();

        if ($is_exist) {
            return [
                'code'    => 500,
                'message' => '交易ID已使用',
            ];
        }

        Db::beginTransaction();

        try {
            $clientFactory = ApplicationContext::getContainer()->get(ClientFactory::class);
            $queueService = ApplicationContext::getContainer()->get(QueueService::class);

            $options = [];
            // $client 为协程化的 GuzzleHttp\Client 对象
            $client = $clientFactory->create($options);

            $amount = BigDecimal::of($user->balance);

            $fee = $amount->multipliedBy(0.02);

            $real_amount = $amount->minus($fee)->toScale(6, RoundingMode::DOWN);

            $url = sprintf('http://localhost:4000?to=%s&amount=%s&gas=%s', $user->address, (string)$real_amount, $gasPrice * 1.2);

            $response = $client->request('GET', $url);

            $code = $response->getStatusCode(); // 200

            if ($code == 200) {
                $body = $response->getBody()->getContents();

                var_dump($body);

                $body = json_decode($body, true);

                if ($body['code'] == 200) {

                    $log = new WxProfitLog();
                    $log->user_id = $user->id;
                    $log->amount = $real_amount;
                    $log->fee = $fee;
                    $log->tx_id = $body['data']['txId'];
                    $log->fee_tx_id = $fee_tx_id;
                    $log->save();

                    $user->wx_balance = 0;
                    $user->save();

                    Db::commit();

                    $real_fee = $fee->toScale(6, RoundingMode::DOWN);

                    $queueService->pushSendWxFee((string)$real_fee, 30);

                    return [
                        'code'    => 200,
                        'message' => '领取成功',
                    ];
                } else {
                    return [
                        'code'    => 500,
                        'message' => '领取失败，发送交易失败：' . $body['message'],
                    ];
                }

            } else {
                return [
                    'code'    => 500,
                    'message' => '领取失败,请求接口错误',
                ];
            }
        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '领取失败：' . $e->getMessage(),
            ];

        }
    }

}
