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
use App\Services\UserService;
use App\Utils\HashId;
use App\Utils\MyNumber;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

class PowerController extends AbstractController
{
    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;


    public function index(RequestInterface $request)
    {
        $user = Context::get('user');

        $userService = ApplicationContext::getContainer()->get(UserService::class);

        // 获取全网总算力

        // 累计产出
        $total_mine = User::sum('profit');

        // 获取挖矿算力
        $mine_power = $user->mine_power;
        // 获取分享算力
        $share_power = $userService->getSharePower($user);
        // 获取团队算力
        $team_power = $userService->getTeamPower($user);

        // 累计个人算力
        $total_power = BigDecimal::of($mine_power)->plus($share_power)->plus($team_power);

        // 获取直邀用户数量

        $children = $user->children()->with('child')->get();

        $direct_num  = $userService->getDirectChildrenNum($children);

        // 获取团队有效用户数量
        if ($user->vip_level == 0) {
            $team_num = $userService->getTeamNum($user);
        } else {
            $team_num = $userService->getTeamNodes($user);
        }

        $global_power = $this->getGlobalPower();

        return [
            'code' => 200,
            'message'     => "",
            'data'        => [
                'global' => [
                    'total_power' => MyNumber::formatPower($global_power),
                    'total_mine' => MyNumber::formatCpu($total_mine),
                ],
                'my'     => [
                    'total_power' => MyNumber::formatPower($total_power),
                    'mine_power'  => MyNumber::formatPower($mine_power),
                    'share_power' => MyNumber::formatPower($share_power),
                    'team_power'  => MyNumber::formatPower($team_power),
                    'balance'      => MyNumber::formatCpu($user->balance),
                    'vip_level'   => $user->vip_level,
                    'direct_num'  => $direct_num,
                    'team_num'    => $team_num,
                ]
            ]
        ];
    }

    public function getGlobalPower() {

        $userService = ApplicationContext::getContainer()->get(UserService::class);

        // 获取所有的用户
        $users = User::where('is_valid', '=', 1)
            ->where('mine_power', '>', 0)
            ->get();

        $global_power = BigDecimal::zero();

        foreach ($users as $user) {
            // 获取分享算力
            $share_power = $userService->getSharePower($user);
            // 获取团队算力
            $team_power = $userService->getTeamPower($user);

            $total_power = BigDecimal::of($user->mine_power)->plus($share_power)->plus($team_power);

            $global_power = $global_power->plus($total_power);
        }

        return $global_power;
    }

    public function profit(RequestInterface $request)
    {
        $user = Context::get('user');

        if ($user->profit <= 0) {
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

        Db::beginTransaction();

        try {
            $ethService = make(EthService::class);

            $gasPrice = $ethService->getGasPrice();

            $clientFactory  = ApplicationContext::getContainer()->get(ClientFactory::class);

            $options = [];
            // $client 为协程化的 GuzzleHttp\Client 对象
            $client = $clientFactory->create($options);

            $amount = BigDecimal::of($user->balance);

            $fee = $amount->multipliedBy(0.02);

            $real_amount = $amount->minus($fee)->toScale(6, RoundingMode::DOWN);

            $url = sprintf('http://localhost:3000?to=%s&amount=%s&gas=%s', $user->address, (string)$real_amount, $gasPrice);

            $response = $client->request('GET', $url);

            $code =  $response->getStatusCode(); // 200

            if ($code == 200) {
                $body =  $response->getBody()->getContents();

                var_dump($body);

                $body = json_decode($body, true);

                if ($body['code'] == 200) {

                    $log = new ProfitLog();
                    $log->user_id = $user->id;
                    $log->amount = $real_amount;
                    $log->fee = $fee;
                    $log->tx_id = $body['data']['txId'];
                    $log->save();

                    $user->balance = 0;
                    $user->save();

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
                'message' => '领取失败：'. $e->getMessage(),
            ];

        }
    }

}
