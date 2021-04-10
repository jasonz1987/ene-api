<?php


namespace App\Services;

use Ethereum\EcRecover;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Redis\Redis;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use HyperfExt\Jwt\Manager;
use HyperfExt\Jwt\Payload;
use Jenssegers\Optimus\Optimus;
use Hyperf\Di\Annotation\Inject;

class ConfigService
{
    /**
     * @Inject()
     * @var ContainerInterface
     */
    private $container;

    /**
     * 获取nonce
     *
     * @param $address
     * @return bool|mixed|string
     */
    public function getKey($key) {
       $redis = $this->container->get(Redis::class);
       return $redis->hGet('soke_database_configs', $key);
    }


    public function setLimit($uid, $key,$time = 10) {
        $redis = $this->container->get(Redis::class);
        return $redis->set(sprintf("%s:%d", $uid, $key), time(), ['nx', 'ex' => $time]);
    }

}