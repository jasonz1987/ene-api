<?php


namespace App\Services;


use App\Helpers\MyConfig;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;
use phpseclib\Math\BigInteger;
use Web3\Contract;
use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\Address;
use Web3\Contracts\Types\Boolean;
use Web3\Contracts\Types\Bytes;
use Web3\Contracts\Types\DynamicBytes;
use Web3\Contracts\Types\Integer;
use Web3\Contracts\Types\Str;
use Web3\Contracts\Types\Uinteger;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;

class EthService
{
    protected $web3;

//    protected $abi = '[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_upgradedAddress","type":"address"}],"name":"deprecate","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_spender","type":"address"},{"name":"_value","type":"uint256"}],"name":"approve","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"deprecated","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_evilUser","type":"address"}],"name":"addBlackList","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_from","type":"address"},{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transferFrom","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"upgradedAddress","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"balances","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"maximumFee","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"_totalSupply","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"unpause","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"_maker","type":"address"}],"name":"getBlackListStatus","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"},{"name":"","type":"address"}],"name":"allowed","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"paused","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"who","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"pause","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"getOwner","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transfer","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"newBasisPoints","type":"uint256"},{"name":"newMaxFee","type":"uint256"}],"name":"setParams","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"amount","type":"uint256"}],"name":"issue","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"amount","type":"uint256"}],"name":"redeem","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"},{"name":"_spender","type":"address"}],"name":"allowance","outputs":[{"name":"remaining","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"basisPointsRate","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"isBlackListed","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_clearedUser","type":"address"}],"name":"removeBlackList","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"MAX_UINT","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_blackListedUser","type":"address"}],"name":"destroyBlackFunds","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"inputs":[{"name":"_initialSupply","type":"uint256"},{"name":"_name","type":"string"},{"name":"_symbol","type":"string"},{"name":"_decimals","type":"uint256"}],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":false,"name":"amount","type":"uint256"}],"name":"Issue","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"amount","type":"uint256"}],"name":"Redeem","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"newAddress","type":"address"}],"name":"Deprecate","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"feeBasisPoints","type":"uint256"},{"indexed":false,"name":"maxFee","type":"uint256"}],"name":"Params","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"_blackListedUser","type":"address"},{"indexed":false,"name":"_balance","type":"uint256"}],"name":"DestroyedBlackFunds","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"_user","type":"address"}],"name":"AddedBlackList","type":"event"},{"anonymous":false,"inputs":[{"indexed":false,"name":"_user","type":"address"}],"name":"RemovedBlackList","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"owner","type":"address"},{"indexed":true,"name":"spender","type":"address"},{"indexed":false,"name":"value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"from","type":"address"},{"indexed":true,"name":"to","type":"address"},{"indexed":false,"name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"anonymous":false,"inputs":[],"name":"Pause","type":"event"},{"anonymous":false,"inputs":[],"name":"Unpause","type":"event"}]';
//
//    protected $contractAddress = '0x1ee27206637Be5990D6227010d456Ebf577EbfBd';

    protected $abi = '[{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":true,"internalType":"address","name":"spender","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":false,"internalType":"uint256","name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"inputs":[{"internalType":"address","name":"owner","type":"address"},{"internalType":"address","name":"spender","type":"address"}],"name":"allowance","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"approve","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"subtractedValue","type":"uint256"}],"name":"decreaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"getOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"addedValue","type":"uint256"}],"name":"increaseAllowance","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"sender","type":"address"},{"internalType":"address","name":"recipient","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transferFrom","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"}]';

    protected $contractAddress = '0x80e9BFA80347DBE1643Dc4fb62A75eDBcC319e15';

    protected $gasLimit = 60000;

    public function __construct()
    {
        $this->web3 = new Web3(new HttpProvider(new HttpRequestManager(config('global.okt_provider_server'), 10)));
    }

    /**
     * 获取钱包nonce
     *
     * @param $fromAccount
     * @return null
     */
    public function getNonce($fromAccount) {
        $nonce = null;

        $this->web3->getEth()->getTransactionCount($fromAccount, 'pending', function ($err, $result) use (&$nonce) {
            if ($err) {
                throw new \Exception('获取nonce失败');
                return;
            }

            $nonce = $result->toString();
        });

        return $nonce;
    }

    /**
     * 获取gasPrice
     *
     * @return null
     */
    public function getGasPrice() {
        $price = null;

        $this->web3->getEth()->gasPrice(function ($err, $gasPrice) use ( &$price) {
            if ($err !== null) {
                throw new \Exception('获取gasPrice失败');
                return;
            }

            $price = $gasPrice->toString();
        });

        return $price;
    }

    /**
     * 获取eth余额
     *
     * @return null
     */
    public function getEthBalance($account) {
        $balance = null;

        $this->web3->getEth()->getBalance($account, function ($err, $ethBalance) use ( &$balance) {
            if ($err !== null) {
                throw new \Exception('获取余额失败失败');
                return;
            }

            $balance = $ethBalance->toString();
        });

        return $balance;
    }

    /**
     * 获取token余额
     *
     * @param $account
     * @return null
     */
    public function getTokenBalance($account) {
        $balance = null;

        $contract = new Contract($this->web3->provider, $this->abi);

        $contract->at($this->contractAddress)->call('balanceOf', $account, [
            'from' => $account
        ], function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \Exception('获取余额失败失败');
                return;
            }

            $balance = $result[0]->toString();
        });

        return $balance;
    }

    /**
     * 构造原始交易
     *
     * @param $fromAccount
     * @param $toAccount
     * @param $amount
     * @param $privateKey
     * @param $nonce
     * @param $gasPrice
     * @return string
     */
    public function getEthRawTransaction($fromAccount, $toAccount, $amount, $privateKey, $nonce, $gasPrice)
    {
//        $gasPrice = (string)(floor($gasPrice * 1.4));

        $raw = [
            'from'     => $fromAccount,
            'to'       => $toAccount,
            'value'    => Utils::toHex(new BigInteger($amount), true),
                //'gas' => Utils::toHex(90000, true),
            'gasLimit' => Utils::toHex($this->gasLimit, true),
            'gasPrice' => Utils::toHex($gasPrice, true),
            'nonce'    => Utils::toHex($nonce, true),
            'chainId'  => 65,
            'data'     => ''
        ];

        Log::info("提交交易数据", [
            'raw' => $raw
        ]);

        $txreq = new \Web3p\EthereumTx\Transaction($raw);

        $signed_transaction = '0x' . $txreq->sign('0x' . $privateKey);

        return $signed_transaction;
    }

    /**
     * 获取旷工费
     *
     * @param $gasPrice
     * @return float|int
     */
    public function getGasFee($gasPrice) {
        return $this->gasLimit * $gasPrice ;
    }

    /**
     * 获取旷工费
     *
     * @param $gasPrice
     * @return float|int
     */
    public function getGasLimit() {
        return $this->gasLimit;
    }


    /**
     * 构造原始交易
     *
     * @param $fromAccount
     * @param $toAccount
     * @param $amount
     * @param $privateKey
     * @param $nonce
     * @param $gasPrice
     * @return string
     */
    public function getTokenRawTransaction($fromAccount, $toAccount, $amount, $privateKey, $nonce, $gasPrice)
    {
//        $gasPrice = (string)(floor($gasPrice * 1.4));

            $abi = new Ethabi([
                'address'      => new Address,
                'bool'         => new Boolean,
                'bytes'        => new Bytes,
                'dynamicBytes' => new DynamicBytes,
                'int'          => new Integer,
                'string'       => new Str,
                'uint'         => new Uinteger
            ]);

            $data = '0xa9059cbb' . str_replace('0x', '', $abi->encodeParameter('address', strtolower($toAccount))) . str_replace('0x', '', $abi->encodeParameter('uint256', $amount));

        $raw = [
            'from'     => $fromAccount,
            'to'       => $this->contractAddress,
            'value'    => 0,
            //'gas' => Utils::toHex(90000, true),
            'gasLimit' => Utils::toHex($this->gasLimit, true),
            'gasPrice' => Utils::toHex($gasPrice, true),
            'nonce'    => Utils::toHex($nonce, true),
            'chainId'  => 65,
            'data'     => $data
        ];

        Log::info("提交交易数据", [
            'raw' => $raw
        ]);

        $txreq = new \Web3p\EthereumTx\Transaction($raw);

        $signed_transaction = '0x' . $txreq->sign('0x' . $privateKey);

        return $signed_transaction;
    }

    /**
     * 广播离线交易
     *
     * @param $signed
     * @return null
     */
    public function sendRawTransaction($signed) {
        $trasaction_id = null;

        $this->web3->getEth()->sendRawTransaction($signed, function ($err, $result) use(&$trasaction_id) {
            if ($err !== null) {
                if ($err->getMessage() == 'insufficient funds for gas * price + value') {
                    throw new \Exception('账户余额不足');
                } else {
                    throw new \Exception($err->getMessage());
                }
                return;
            }

            $trasaction_id = $result;

        });

        return $trasaction_id;
    }

    public function sendEth($fromAccount, $toAccount, $amount, $privateKey) {
        $nonce = $this->getNonce($fromAccount);


        if (is_null($nonce)) {
            throw new \Exception("获取钱包Nonce失败");
        };

        // 获取当前的GAS
        $gasPrice = $this->getGasPrice();

        if (is_null($gasPrice)) {
            throw new \Exception("获取gasPrice失败");
        }

        $amount = BigDecimal::of($amount)->toScale(6, RoundingMode::DOWN)->toFloat();

        $amount = Utils::toWei((string)$amount, 'ether');

        $transaction_raw = $this->getEthRawTransaction($fromAccount, $toAccount, $amount,$privateKey, $nonce, $gasPrice);

        $transaction_id = $this->sendRawTransaction($transaction_raw);

        return $transaction_id;
    }


    public function sendToken($fromAccount, $toAccount, $amount, $privateKey) {
        $nonce = $this->getNonce($fromAccount);


        if (is_null($nonce)) {
            throw new \Exception("获取钱包Nonce失败");
        };

        // 获取当前的GAS
        $gasPrice = $this->getGasPrice();

        if (is_null($gasPrice)) {
            throw new \Exception("获取gasPrice失败");
        }

        $amount = BigDecimal::of($amount)->toScale(6, RoundingMode::DOWN)->toFloat();

        $amount = Utils::toWei((string)$amount, 'mwei');

        $transaction_raw = $this->getTokenRawTransaction($fromAccount, $toAccount, $amount,$privateKey, $nonce, $gasPrice);

        $transaction_id = $this->sendRawTransaction($transaction_raw);

        return $transaction_id;
    }

    /**
     * 获取交易收据
     *
     * @param $hash
     */
    public function getTransactionReceipt($hash) {
        $transaction = null;

        $this->web3->getEth()->getTransactionReceipt($hash,  function ($err, $result) use (&$transaction){

            if ($err) {
                throw new \Exception($err->getMessage());
            }

            $transaction = $result;
        });

        return $transaction;
    }

    /**
     * 获取交易收据
     *
     * @param $hash
     */
    public function getTransactionInfo($hash) {
        $transaction = null;

        $this->web3->getEth()->getTransactionByHash($hash,  function ($err, $result) use (&$transaction){

            if ($err) {
                throw new \Exception($err->getMessage());
            }

            $transaction = $result;
        });

        return $transaction;
    }

    public function decodeRawTransaction($bytecode){

        $abi = new Ethabi([
            'address'      => new Address,
            'bool'         => new Boolean,
            'bytes'        => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int'          => new Integer,
            'string'       => new Str,
            'uint'         => new Uinteger
        ]);

        $contract = new Contract($this->web3->provider, $abi);

//        $contract->bytecode($bytecode)->new($params, $callback);

    }

    public function filterTransaction() {
        dd($this->web3->getEth()->txpool);
        $this->web3->getEth()->newFilter([
            'fromBlock'=>"pending",
            'toBlock'=>"latest",
            'address'=>"0xCF3A497eD4f1204b3E94Ff89A628567a5b18aC90",
        ],function ($err, $filter) {
            if ($err !== null) {
                // infura banned us to new pending transaction filter
                dd($err);
            }
            dd($filter);
        });
    }

    /**
     * 解析收据
     *
     * @param $receipt
     */
    public function decodeReceipt($receipt) {
        $abi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger
        ]);

        $logs = $receipt->logs;

        $log = $logs[0];


        $data = $log->data;
        $topics = $log->topics;

        $value = $abi->decodeParameter('uint256', $data)->toString();

        $to = $topics[2];

        $to = '0x' . substr($to, -40);

        return [
            'from'  =>  $receipt->from,
            'to'    =>  $to,
            'value' =>  $value
        ];

    }

}