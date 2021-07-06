<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\DepositLog;
use App\Model\User;
use App\Service\QueueService;
use App\Services\ConfigService;
use App\Services\EthService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use http\Exception\RuntimeException;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerInterface;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;

/**
 * @Command
 */
class UnitTest extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('unit:test');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('单元测试');
    }

    public function handle()
    {
        $ethService = make(EthService::class);

        $transaction_id = $ethService->sendToken('0xCF3A497eD4f1204b3E94Ff89A628567a5b18aC90', '0x742499427a41087532dF277A0B9581F90cA2e78F', 10, '8e93cc10a39a7bdcd6c2ea8535083ded724b3d1a85d30837b206e7b633a83b46');



//        $txreq = new \Web3p\EthereumTx\Transaction('0xa9059cbb000000000000000000000000558a9ed5f4c6dc2f357451f323dca25ce6eea22f00000000000000000000000000000000000000000000000000000000000f4240');
//
//        var_dump($txreq);
    }
}
