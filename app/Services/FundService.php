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

class FundService
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
    public function getProductPeriod($product, $period) {
        $periods = json_decode($product->periods, true);

        foreach($periods as $k=>$v) {
            if ($v['period'] == $period) {
                return $v;
            }
        }

        return null;
    }

}