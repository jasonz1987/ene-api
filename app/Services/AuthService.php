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

class AuthService
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
    public function getNonce($address) {
        $redis = $this->container->get(Redis::class);

        $nonce = $redis->get('user.nonce:' . $address);

        if (!$nonce) {
            $nonce = $this->createNonce($address);
        }

        return $nonce;
    }

    /**
     * 生成nonce
     *
     * @param $address
     * @return string
     */
    public function createNonce($address) {
        $redis = $this->container->get(Redis::class);
        $nonce = sha1(time().$address . mt_rand(0,100));
        $redis->set('user.nonce:' . $address, $nonce, [
            'ex'    =>  60*10
        ]);
        return $nonce;
    }

    public function verifySignature($message, $address, $signature) {
        $valid = EcRecover::personalVerifyEcRecover($message,  $signature,  $address);
        return $valid;
    }

    public function createToken($user) {
        $jwtFactory = $this->container->get(JwtFactoryInterface::class);

        $jwt = $jwtFactory->make();

        $token = $jwt->fromUser($user);

        return $token;
    }
}