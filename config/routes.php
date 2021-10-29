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
    Router::addRoute(['GET', 'HEAD'], '/power/index', 'App\Controller\Api\PowerController@index');
    Router::addRoute(['POST', 'HEAD'], '/power/profit', 'App\Controller\Api\PowerController@profit');
    Router::addRoute(['GET', 'HEAD'], '/power/profit/logs', 'App\Controller\Api\PowerController@profitLogs');
    Router::addRoute(['GET', 'HEAD'], '/wx/index', 'App\Controller\Api\WxController@index');
    Router::addRoute(['POST', 'HEAD'], '/wx/profit', 'App\Controller\Api\WxController@profit');

    Router::addRoute(['GET', 'HEAD'], '/burn/index', 'App\Controller\Api\BurnController@index');
    Router::addRoute(['GET', 'HEAD'], '/burn/share', 'App\Controller\Api\BurnController@share');

    Router::addRoute(['GET', 'HEAD'], '/dao/index', 'App\Controller\Api\DaoController@index');
    Router::addRoute(['POST', 'HEAD'], '/dao/profit', 'App\Controller\Api\DaoController@profit');

},
    ['middleware' => [AuthMiddleware::class]]
);

Router::get('/favicon.ico', function () {
    return '';
});
