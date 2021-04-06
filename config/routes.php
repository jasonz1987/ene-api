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

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::addRoute(['POST', 'HEAD'], '/api/v1/auth/nonce', 'App\Controller\AuthController@nonce');
Router::addRoute(['POST', 'HEAD'], '/api/v1/auth/token', 'App\Controller\AuthController@token');

Router::addGroup('/api/v1',function (){
    Router::addRoute(['GET', 'HEAD'], '/contract/indexes', 'App\Controller\ContractController@indexes');
    Router::addRoute(['GET', 'HEAD'], '/contract/positions', 'App\Controller\ContractController@positions');
    Router::addRoute(['GET', 'HEAD'], '/contract/orders', 'App\Controller\ContractController@orders');
    Router::addRoute(['GET',  'HEAD'], '/contract/index/kline', 'App\Controller\ContractController@indexKline');
    Router::addRoute(['GET',  'HEAD'], '/contract/index/market', 'App\Controller\ContractController@indexMarket');
    Router::addRoute(['POST', 'HEAD'], '/contract/order/create', 'App\Controller\ContractController@createOrder');
    Router::addRoute(['POST', 'HEAD'], '/contract/order/cancel', 'App\Controller\ContractController@cancelOrder');

    Router::addRoute(['GET', 'HEAD'], '/fund/products', 'App\Controller\FundController@products');
    Router::addRoute(['POST', 'HEAD'], '/fund/product/buy/order', 'App\Controller\FundController@buyOrder');
    Router::addRoute(['POST', 'HEAD'], '/fund/product/buy', 'App\Controller\FundController@buy');

},
    ['middleware' => [AuthMiddleware::class]]
);

Router::get('/favicon.ico', function () {
    return '';
});

Router::addServer('ws', function () {
    Router::get('/ws', 'App\Controller\WebSocketController');
});
