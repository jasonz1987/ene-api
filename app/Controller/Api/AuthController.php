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
                'address.required' => '请输入钱包地址',
                'address.regex' => '钱包地址格式不正确',
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
            'message' => "成功",
            'data'    => [
                'is_bind' => $user && $user->source_address ? true : false
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
                'address.required' => '请输入钱包地址',
                'address.regex' => '钱包地址格式不正确',
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
                'source'    => 'regex:/^(0x)?[0-9a-zA-Z]{40}$/',
                'signature' => 'required|regex:/^(0x)?[0-9a-zA-Z]{130}$/',
            ],
            [
                'address.required'   => '请输入钱包地址',
                'signature.required' => '请输入签名',
                'address.regex'      => '钱包地址不合法',
                'signature.regex'    => '签名格式不合法',
                'source.required'    => '请输入好友钱包地址',
                'source.regex'       => '好友钱包地址不正确',
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
            if ($request->has('source')) {
                if (!$user->source_address) {
                    $source_address = strtolower($request->input('source'));
                    // 查询原地址是否存在
                    $source = User::where('address', '=', $source_address)
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

                    $this->insertChildren($user, $source);
                    $user->source_address = $source->address;
                    $user->save();
                }
            } else {
                if (!$user->source_address) {
                    return [
                        'code'    => 500,
                        'message' => '未绑定好友地址'
                    ];
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
                'parent_id'  => $source->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        $result[] = [
            'user_id'    => $source->id,
            'child_id'   => $user->id,
            'level'      => 1,
            'parent_id'  => $source->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        if ($result) {
            InvitationLog::insert($result);
        }
    }


}
