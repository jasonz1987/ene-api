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

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Model\InvitationLog;
use App\Model\User;
use App\Services\AuthService;
use App\Services\UserService;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Context;
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

    /**
     * @Inject()
     * @var UserService
     */
    protected $userService;

    public function info(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'address' => 'required|regex:/^(0x)?[0-9a-zA-Z]{40}$/',
            ],
            [
                'address' => 'address is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $address = strtolower($request->input('address'));

        $user = User::where('address', '=', $address)
            ->first();

        return [
            'code'    => 200,
            'message' => "success",
            'data'    => [
                'is_bind' => $user && $user->source ? true : false
            ]
        ];
    }

    public function nonce(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'address' => 'required|regex:/^(0x)?[0-9a-zA-Z]{40}$/',
            ],
            [
                'address' => 'address is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $address = strtolower($request->input('address'));

        $nonce = $this->authService->getNonce($address);

        if (!$nonce) {
            $nonce = $this->authService->createNonce($address);
        }

        return [
            'code'    => 200,
            'message' => "success",
            'data'    => [
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
                'source'    => 'required|regex:/^(0x)?[0-9a-zA-Z]{40}$/',
                'signature' => 'required|regex:/^(0x)?[0-9a-zA-Z]{130}$/',
            ],
            [
                'address.required'   => 'address is required',
                'signature.required' => 'signature is required',
                'address.regex'      => 'address format error',
                'signature.regex'    => 'signature format error',
                'source.required'    => 'source is required',
                'source.regex'       => 'source format error',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $address = strtolower($request->input('address'));
        $signature = $request->input('signature');

        $nonce = $this->authService->getNonce($address);

        if (!$nonce) {
            return [
                'code'    => 500,
                'message' => 'nonce not exist'
            ];
        }

        try {
            // 验证签名
            if (!$this->authService->verifySignature($nonce, $address, $signature)) {
                return [
                    'code'    => 500,
                    'message' => '签名验证失败'
                ];
            }

        } catch (\Exception $e) {
            return [
                'code'    => 500,
                'message' => $e->getMessage()
            ];
        }

        Db::beginTransaction();

        try {

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
            if ($request->has('address')) {
                if (!$user->source_address) {
                    $source_address = strtolower($request->input('source'));
                    $this->getSource($source_address, $source);
                    $this->insertChildren($user, $source);
                    $user->source_address = $source->address;
                    $user->save();
                }
            }

            Db::commit();

            $token = $this->authService->createToken($user);

            return [
                'code'    => 200,
                'message' => "认证成功",
                'data'    => [
                    'access_token' => $token,
                    'expired_at'   => time() + 60 * 60 * 24,
                ]
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => $e->getMessage(),
            ];
        }

    }

    public function bind(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'source' => 'required',
            ],
            [
                'source.required' => 'id is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return [
                'code'    => 400,
                'message' => $errorMessage,
            ];
        }

        $user = Context::get('user');

        if ($user->source_address) {
            return [
                'code'    => 500,
                'message' => '请勿重复绑定',
            ];
        }

        $this->getSource($request->input('source'), $source);

        Db::beginTransaction();

        try {
            $user->source_address = $source->address;
            $user->save();

            $this->insertChildren($user, $source);
            $this->userService->updateSharePower($source);

            Db::commit();

            return [
                'code'    => 200,
                'message' => '绑定成功',
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            return [
                'code'    => 500,
                'message' => '绑定失败',
            ];
        }
    }

    protected function getSource($address, &$source)
    {
        // 查询原地址是否存在
        $source = User::where('address', '=', $address)
            ->first();

        if (!$source) {
            return [
                'code'    => 500,
                'message' => '源地址不存在',
            ];
        }

        if ($source->is_valid == 0) {
            return [
                'code'    => 500,
                'message' => '源地址不是有效账号',
            ];
        }
    }

    protected function insertChildren($user, $source)
    {
        // 获取父级用户所有的父级记录
        $parents = InvitationLog::where('child_id', '=', $source->id)
            ->get();

        $result = [];

        foreach ($parents as $v) {
            $result[] = [
                'user_id'    => $v->user_id,
                'child_id'   => $user->id,
                'level'      => $v->level + 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        $result[] = [
            'user_id'    => $source->id,
            'child_id'   => $user->id,
            'level'      => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        if ($result) {
            InvitationLog::insert($result);
        }
    }


}
