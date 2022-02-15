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

Router::addRoute(['GET', 'HEAD'], '/api/auth/info', 'App\Controller\Api\AuthController@info');
Router::addRoute(['POST', 'HEAD'], '/api/auth/nonce', 'App\Controller\Api\AuthController@nonce');
Router::addRoute(['POST', 'HEAD'], '/api/auth/token', 'App\Controller\Api\AuthController@token');

Router::addGroup('/api',function (){
    Router::addRoute(['GET', 'HEAD'], '/mine/index', 'App\Controller\Api\MineController@index');
    Router::addRoute(['POST', 'HEAD'], '/mine/profit', 'App\Controller\Api\MineController@profit');
    Router::addRoute(['POST', 'HEAD'], '/lp/index', 'App\Controller\Api\LpController@profit');
    Router::addRoute(['POST', 'HEAD'], '/lp/profit', 'App\Controller\Api\LpController@profit');

},
    ['middleware' => [AuthMiddleware::class]]
);

Router::get('/favicon.ico', function () {
    return '';
});
