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
use App\Model\StakeLog;
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

class DaoController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        $log = StakeLog::where('address', '=', $user->address)
            ->where('status', '=', 0)
            ->selectRaw('SUM(user_amount+user_reward) as balance, SUM(user_balance) as reward')
            ->first();

        $addresses = [env('REWARD_ADDRESS')];

        return [
            'code'    => 200,
            'message' => "",
            'data'    => [
                'balance'     => $log->balance ? MyNumber::formatCpu($log->balance) : 0,
                'reward'      => $log->reward ? MyNumber::formatCpu($log->reward) : 0,
                'fee_address' => $addresses[array_rand($addresses)],
                'fee_rate'    => 2,
                'gas_limit'   => 100000
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

            $fee = 0;

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
                    $log->type = 2;
                    $log->save();

                    StakeLog::where('address', '=', $user->address)
                        ->update(['status'=>1]);

                    Db::commit();

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
