<?php


namespace App\Services;

use Carbon\Carbon;
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
        return $redis->set(sprintf("%s:%s", $uid, $key), time(), ['nx', 'ex' => $time]);
    }

    public function getLastBlockNumber() {
        $redis = $this->container->get(Redis::class);
        return $redis->get('latest_block_number');
    }

    public function setLastBlockNumber($number) {
        $redis = $this->container->get(Redis::class);
        return $redis->set('latest_block_number', $number);
    }

    public function setEnePrice($price) {
        $redis = $this->container->get(Redis::class);
        return $redis->set('ene_price', $price);
    }

    public function setEneLPBalance($balance) {
        $redis = $this->container->get(Redis::class);
        return $redis->set('ene_lp_balance', $balance);
    }

    public function setWithdrawLimit($uid,$time = 10) {
        $redis = $this->container->get(Redis::class);
        return $redis->set(sprintf("withdraw-limit:%s", $uid,), time(), ['nx', 'ex' => $time]);
    }

    public function isWxMineStart() {
        return Carbon::now() >= Carbon::parse("2021-09-20 00:00:00");
    }

}