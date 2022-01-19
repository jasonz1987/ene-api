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
use App\Helpers\MyConfig;
use App\Model\PowerRewardLog;
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

class MineController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        $ethService = make(EthService::class);

        $userService = ApplicationContext::getContainer()->get(UserService::class);

        // 获取全网总算力
        $collection = $user->children()->with('child')->get();

        // 累计产出
        $total_mine = User::sum('profit');

        $total_mine = round($total_mine / 50) * 50;

        // 获取挖矿算力
        $mine_power = $user->mine_power;
        // 获取分享算力
        $share_power = $userService->getSharePower($user, $collection);
        // 获取团队算力
        $team_power = $userService->getTeamPower($user, $collection);

        // 累计个人算力
        $total_power = BigDecimal::of($mine_power)->plus($share_power)->plus($team_power);

        // 获取直邀用户数量

        $direct_num = $userService->getDirectChildrenNum($collection);

        // 获取团队有效用户数量
        if ($user->vip_level == 0) {
            $team_num = $userService->getTeamNum($user, $collection);
        } else {
            $team_num = $userService->getTeamNodes($user, $collection);
        }

        $global_power = $this->getGlobalPower();

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'global' => [
                    'total_power' => MyNumber::formatPower($global_power),
                    'fee_address' => env('REWARD_ADDRESS')
                ],
                'my'     => [
                    'total_power'     => MyNumber::formatPower($total_power),
                    'equipment_power' => MyNumber::formatPower($user->equipment_power),
                    'share_power'     => MyNumber::formatPower($share_power),
                    'team_power'      => MyNumber::formatPower($team_power),
                    'balance'         => MyNumber::formatCpu($user->balance),
                    'remain_bonus'    => MyNumber::formatPower(0),
                    'team_level'      => $user->team_level,
                    'team_num'        => $team_num,
                ]
            ]
        ];
    }

    public function getGlobalPower()
    {

        $userService = ApplicationContext::getContainer()->get(UserService::class);
        $redis = ApplicationContext::getContainer()->get(Redis::class);

        if ($redis->get("global_power")) {
            return $redis->get("global_power");
        }

        // 获取所有的用户
        $users = User::where('is_valid', '=', 1)
            ->where('mine_power', '>', 0)
            ->get();

        $global_power = BigDecimal::zero();

        foreach ($users as $user) {
//            $collection = $user->children()->with('child')->get();
//
//            // 获取分享算力
//            $share_power = $userService->getSharePower($user, $collection);
//            // 获取团队算力
//            $team_power = $userService->getTeamPower($user, $collection);

            $total_power = BigDecimal::of($user->mine_power)->plus($user->share_power)->plus($user->team_power);

            $global_power = $global_power->plus($total_power);
        }

        $redis->set("global_power", (string)$global_power, 300);

        return $global_power;
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

        if (BigDecimal::of($user->balance)->isLessThan(0.000001)) {
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

        var_dump($transaction);

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

//        if ($transaction->to != strtolower(env('REWARD_ADDRESS'))) {
//            return [
//                'code'    => 500,
//                'message' => '交易不合法',
//            ];
//        }

//        $fee = BigNumber::of($gasPrice)->multipliedBy($ethService->getGasLimit());
//
//
//        if (BigDecimal::of($transaction->value)->isLessThan($fee)) {
//            return [
//                'code'    => 500,
//                'message' => '手续费不足，请重试',
//            ];
//        }

        $is_exist = ProfitLog::where('fee_tx_id', $fee_tx_id)
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

            $url = sprintf('http://localhost:3000?to=%s&amount=%s&gas=%s', $user->address, (string)$real_amount, $gasPrice * 1.2);

            $response = $client->request('GET', $url);

            $code = $response->getStatusCode(); // 200

            if ($code == 200) {
                $body = $response->getBody()->getContents();

                var_dump($body);

                $body = json_decode($body, true);

                if ($body['code'] == 200) {

                    $log = new ProfitLog();
                    $log->user_id = $user->id;
                    $log->amount = $real_amount;
                    $log->fee = $fee;
                    $log->tx_id = $body['data']['txId'];
                    $log->fee_tx_id = $fee_tx_id;
                    $log->save();

                    $user->balance = 0;
                    $user->save();

                    Db::commit();

                    $real_fee = $fee->toScale(6, RoundingMode::DOWN);

                    $queueService->pushSendFee((string)$real_fee, 30);

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

    public function profitLogs(RequestInterface $request)
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

        $logs = ProfitLog::where('user_id', '=', $user->id)
            ->orderBy('id', 'desc')
            ->paginate((int)$request->input('per_page', 10), ['*'], 'page', (int)$request->input('page'));

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
                'amount'     => MyNumber::formatCpu($log->amount),
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
