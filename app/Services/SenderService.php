<?php


declare(strict_types=1);

namespace App\Services;

use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use Psr\Container\ContainerInterface;
use Hyperf\Di\Annotation\Inject;

class SenderService
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    public function push(int $fd, $data) {

        $sender = $this->container->get(Sender::class);

        $sender->push($fd, $data);

        if ($sender->check($fd)) {
            $sender->push($fd, $data);
        } else {
           $this->remove($fd);
        }
    }

    public function remove(int $fd) {

        $redis = $this->container->get(Redis::class);

        $uid = $redis->hGet('fd:users', strval($fd));

        if ($uid) {
            $redis->hDel('user:fds', $uid);
        }
        $redis->hDel('fd:users', $fd);
    }
}