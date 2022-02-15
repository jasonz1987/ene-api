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


use App\Controller\AbstractController;
use App\Model\LpProfitLog;
use App\Model\ProfitLog;
use App\Model\User;
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

class LpController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        // 获取挖矿算力
        $stake_lp = $user->stake_lp;
        // 获取分享算力
        $share_lp = $user->share_lp;

        // 累计个人算力
        $total_lp = BigDecimal::of($stake_lp)->plus($share_lp);

        $global_lp = User::all()->sum(function ($t) {
            if ($t->stake_lp > 0) {
                return $t->stake_lp + $t->share_lp;
            } else {
                return 0;
            }
        });

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'global' => [
                    'total_lp' => MyNumber::formatPower($global_lp),
                ],
                'my'     => [
                    'total_lp' => MyNumber::formatPower($total_lp),
                    'stake_lp' => MyNumber::formatPower($stake_lp),
                    'share_lp' => MyNumber::formatPower($share_lp),
                    'balance'  => MyNumber::formatCpu($user->lp_balance),
                ]
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

        if (BigDecimal::of($user->lp_balance)->isLessThan(0.000001)) {
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

        $is_exist = LpProfitLog::where('fee_tx_id', $fee_tx_id)
            ->first();

        if ($is_exist) {
            return [
                'code'    => 500,
                'message' => '交易ID已使用',
            ];
        }

        Db::beginTransaction();

        try {
            $amount = BigDecimal::of($user->balance);

            $fee = $amount->multipliedBy(0.01);

            $log = new ProfitLog();
            $log->user_id = $user->id;
            $log->amount = $amount;
            $log->fee = $fee;
            $log->fee_tx_id = $fee_tx_id;
            $log->save();

            $user->lp_balance = 0;
            $user->save();

            Db::commit();

            $queueService = ApplicationContext::getContainer()->get(QueueService::class);
            $queueService->pushLpWithdraw($log->id, 1);

            return [
                'code'    => 200,
                'message' => '领取成功',
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '领取失败：' . $e->getMessage(),
            ];

        }
    }
}
