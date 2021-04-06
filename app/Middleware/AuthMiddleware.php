<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\User;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Context;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    public function __construct(ContainerInterface $container,  HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response  = $response;
        $this->request = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($this->request->path(), ['api/v1/contract/indexes', 'api/v1/contract/index/kline', 'api/v1/contract/index/market'])) {
            return $handler->handle($request);
        }

        $jwtFactory = $this->container->get(JwtFactoryInterface::class);
        $jwt = $jwtFactory->make();

        if (!$jwt->check()) {
            return $this->response->json(
                [
                    'code'    => 403,
                    'message' => '认证失败'
                ]
            );
        }

        $uid = $jwt->getPayload()->get('sub');

        if (!$uid) {
            return $this->response->json(
                [
                    'code'    => 403,
                    'message' => '非法请求'
                ]
            );
        }

        $user = User::find($uid);

        if (!$user) {
            return $this->response->json(
                [
                    'code'    => 403,
                    'message' => '用户未注册'
                ]
            );
        }

        Context::set('user', $user);

        return $handler->handle($request);
    }
}