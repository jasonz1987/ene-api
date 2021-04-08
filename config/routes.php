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
use Hyperf\HttpServer\Router\Router;
use App\Middleware\AuthMiddleware;

Router::addRoute(['GET', 'HEAD'], '/api/v1/index/index', 'App\Controller\IndexController@index');
Router::addRoute(['POST', 'HEAD'], '/api/v1/auth/nonce', 'App\Controller\AuthController@nonce');
Router::addRoute(['POST', 'HEAD'], '/api/v1/auth/token', 'App\Controller\AuthController@token');

Router::addGroup('/api/v1',function () {
    Router::addRoute(['GET', 'HEAD'], '/contract/indexes', 'App\Controller\ContractController@indexes');
    Router::addRoute(['GET', 'HEAD'], '/fund/products', 'App\Controller\FundController@products');
    Router::addRoute(['GET',  'HEAD'], '/contract/index/kline', 'App\Controller\ContractController@indexKline');
    Router::addRoute(['GET',  'HEAD'], '/contract/index/market', 'App\Controller\ContractController@indexMarket');
},
    []
);
Router::addGroup('/api/v1',function (){

    Router::addRoute(['POST', 'HEAD'], '/api/v1/index/recharge', 'App\Controller\IndexController@recharge');
    Router::addRoute(['POST', 'HEAD'], '/api/v1/index/recharge/order', 'App\Controller\IndexController@rechargeOrder');
    Router::addRoute(['POST', 'HEAD'], '/api/v1/index/withdraw', 'App\Controller\IndexController@withdraw');

    Router::addRoute(['GET', 'HEAD'], '/contract/positions', 'App\Controller\ContractController@positions');
    Router::addRoute(['GET', 'HEAD'], '/contract/orders', 'App\Controller\ContractController@orders');
    Router::addRoute(['POST', 'HEAD'], '/contract/order/create', 'App\Controller\ContractController@createOrder');
    Router::addRoute(['POST', 'HEAD'], '/contract/order/cancel', 'App\Controller\ContractController@cancelOrder');
    Router::addRoute(['POST', 'HEAD'], '/contract/position/close', 'App\Controller\ContractController@closePosition');

    Router::addRoute(['POST', 'HEAD'], '/fund/product/buy/order', 'App\Controller\FundController@buyOrder');
    Router::addRoute(['POST', 'HEAD'], '/fund/product/buy', 'App\Controller\FundController@buy');
    Router::addRoute(['POST', 'HEAD'], '/fund/order/redeem', 'App\Controller\FundController@redeem');
    Router::addRoute(['GET', 'HEAD'], '/fund/buy/logs', 'App\Controller\FundController@buyLogs');
    Router::addRoute(['GET', 'HEAD'], '/fund/redeem/logs', 'App\Controller\FundController@redeemLogs');
    Router::addRoute(['GET', 'HEAD'], '/fund/reward/logs', 'App\Controller\FundController@rewardLogs');

    Router::addRoute(['POST', 'HEAD'], '/market/pledge', 'App\Controller\MarketController@pledge');
    Router::addRoute(['POST', 'HEAD'], '/market/pledge/order', 'App\Controller\MarketController@pledgeOrder');
    Router::addRoute(['POST', 'HEAD'], '/market/pledge/cancel', 'App\Controller\MarketController@cancelPledge');
    Router::addRoute(['GET', 'HEAD'], '/market/income/logs', 'App\Controller\MarketController@incomeLogs');
    Router::addRoute(['GET', 'HEAD'], '/market/loss/logs', 'App\Controller\MarketController@lossLogs');

    Router::addRoute(['POST', 'HEAD'], '/power/start', 'App\Controller\PowerController@start');
    Router::addRoute(['POST', 'HEAD'], '/power/stop', 'App\Controller\PowerController@stop');
    Router::addRoute(['GET', 'HEAD'], '/power/reward/logs', 'App\Controller\PowerController@rewardLogs');


},
    ['middleware' => [AuthMiddleware::class]]
);

Router::get('/favicon.ico', function () {
    return '';
});

Router::addServer('ws', function () {
    Router::get('/ws', 'App\Controller\WebsocketController');
});
