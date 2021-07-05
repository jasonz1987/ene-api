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

Router::addRoute(['GET', 'HEAD'], '/api/auth/info', 'App\Controller\AuthController@info');
Router::addRoute(['POST', 'HEAD'], '/api/auth/nonce', 'App\Controller\AuthController@nonce');
Router::addRoute(['POST', 'HEAD'], '/api/auth/token', 'App\Controller\AuthController@token');

Router::addGroup('/api',function (){
    Router::addRoute(['GET', 'HEAD'], '/power/index', 'App\Controller\PowerController@start');
    Router::addRoute(['POST', 'HEAD'], '/power/profit', 'App\Controller\PowerController@stop');

},
    ['middleware' => [AuthMiddleware::class]]
);

Router::get('/favicon.ico', function () {
    return '';
});
