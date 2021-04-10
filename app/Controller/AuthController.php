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

namespace App\Controller;

use App\Model\User;
use App\Services\AuthService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Str;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use phpseclib\Crypt\Hash;

class AuthController extends AbstractController
{

    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * @Inject()
     * @var AuthService
     */
    protected $authService;

    public function nonce(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'address' => 'required',
            ],
            [
                'address' => 'address is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code' => 400,
                'message'     => $errorMessage,
            ];
        }

        $address = $request->input('address');

        $nonce = $this->authService->getNonce($address);

        if (!$nonce) {
            $nonce = $this->authService->createNonce($address);
        }

        return [
            'code' => 200,
            'message'     => "hi",
            'data'        => [
                'nonce'      => $nonce,
                'expired_at' => time() + 60 * 10
            ]
        ];
    }

    public function token(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'address'   => 'required|regex:/^(0x)?[0-9a-zA-Z]{40}$/',
                'signature' => 'required|regex:/^(0x)?[0-9a-zA-Z]{130}$/',
            ],
            [
                'address.required'   => 'address is required',
                'signature.required' => 'signature is required',
                'address.regex'   => 'address format error',
                'signature.regex' => 'signature format error',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code' => 400,
                'message'     => $errorMessage,
            ];
        }

        $address = $request->input('address');
        $signature = $request->input('signature');

        $nonce = $this->authService->getNonce($address);

        if (!$nonce) {
            return [
                'code'    => 500,
                'message' => 'nonce not exist'
            ];
        }

        // 验证签名
        if (!$this->authService->verifySignature($nonce, $address, $signature)) {
            return [
                'code'    => 500,
                'message' => 'signature verify failure'
            ];
        }

        // 判断用户是否存在
        $user = User::where('address', '=', $address)
            ->first();

        if (!$user) {
            $user = new User();
            $user->address = $address;
            $user->password = sha1(Str::random(16));
            $user->save();
        }

        // 生成TOKEN

        $token  = $this->authService->createToken($user);

        return [
            'code'    => 200,
            'message' => "认证成功",
            'data'    => [
                'access_token' => $token,
                'expired_at'   => time() + 60 * 60 * 24
            ]
        ];
    }

}
