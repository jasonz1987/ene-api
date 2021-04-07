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


use App\Model\FundProduct;
use App\Utils\HashId;
use Hyperf\Utils\Context;

class IndexController extends AbstractController
{
    public function index()
    {
        $user = Context::get('user');

        // 指数
        $indexes = FundProduct::orderBy('id')
            ->select('id', 'title', 'periods')
            ->get()
            ->map(function($item){
                $periods = json_decode($item->periods, true);

                if ($periods) {
                    $item['profit'] = $periods[0]['profit'];
                }

                unset($item['periods']);

                $item->id = HashId::encode($item->id);

                return $item;
            })
            ->toArray();

        // 基金
        $funds = FundProduct::orderBy('id')
            ->select('id', 'title', 'periods')
            ->get()
            ->map(function($item){
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
                'power' => [

                ],
                'index' => [

                ],
                'fund'  =>  $funds
            ]
        ];
    }
}
