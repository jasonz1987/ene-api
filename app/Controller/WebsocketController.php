<?php
declare(strict_types=1);

namespace App\Controller;

use Carbon\Carbon;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Jwt;
use HyperfExt\Jwt\JwtFactory;
use HyperfExt\Jwt\Token;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

class WebsocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    public function index()
    {

    }

    public function onMessage($server, Frame $frame): void
    {
        $data = json_decode($frame->data, true);

//        if (isset($data['sub'])) {
//            $server->push($frame->fd, json_encode([
//                "status" => "ok",
//                "subbed" => $data['sub'],
//                "ts"     => Carbon::now()->getPreciseTimestamp(3)
//            ]));
//        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $redis = ApplicationContext::getContainer()->get(Redis::class);
        // 移除订阅
        $uid = $redis->hGet('ws.fd.users', strval($fd));

        if ($uid) {
            $redis->hDel('ws.user.fds', $uid);
        }

        $redis->hDel('ws.fd.users', $fd);

    }

    public function onOpen($server, Request $request): void
    {
        $server->push($request->fd, 'Opened');

        $jwtFactory = ApplicationContext::getContainer()->get(JwtFactoryInterface::class);
        $redis = ApplicationContext::getContainer()->get(Redis::class);
        $jwt = $jwtFactory->make();
        // 鉴权
        if (isset($request->get['token'])) {
            try {
                $uid = $jwt->getPayload()->get('sub');
                $redis->hSet('ws.user.fds', strval($uid), $request->fd);
                $redis->hSet('ws.fd.users',  strval($request->fd), $uid);
            } catch (\Exception $e) {
                $server->disconnect($request->fd, 401);
            }
        } else {
            $redis->hSet('ws.fd.users',  strval($request->fd), "0");
        }
    }
}
