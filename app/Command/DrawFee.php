<?php

declare(strict_types=1);

namespace App\Command;

use _HumbugBoxa9bfddcdef37\Nette\Neon\Exception;
use App\Model\Config;
use App\Model\DepositLog;
use App\Model\FeeLog;
use App\Model\InvitationLog;
use App\Model\User;
use App\Service\QueueService;
use App\Services\ConfigService;
use App\Services\EthService;
use App\Utils\Tron;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use http\Exception\RuntimeException;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;

/**
 * @Command
 */
class DrawFee extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('draw:fee');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('获取LP合约余额');
    }

    public function handle()
    {
        $configService = $this->container->get(ConfigService::class);
        $ethService = $this->container->get(EthService::class);

        $balance = $ethService->getEthBalance("0xE4A0270e1c11abd4929F005d35193C1D9dAcE75e");

        if ($balance) {
            $balance = BigDecimal::of($balance)->dividedBy(1e18, 4 ,RoundingMode::DOWN);

            if ($balance->isGreaterThanOrEqualTo(0.1)) {

                try {
                    $clientFactory  = ApplicationContext::getContainer()->get(ClientFactory::class);

                    $options = [];
                    // $client 为协程化的 GuzzleHttp\Client 对象
                    $client = $clientFactory->create($options);

                    $url = sprintf('http://localhost:4000');

                    \App\Utils\Log::get()->info(sprintf('提现手续费URL：%s' , $url));

                    $response = $client->request('GET', $url);

                    if ($response->getStatusCode() == 200) {
                        \App\Utils\Log::get()->info(sprintf('提现手续费成功：%s' , $response->getBody()->getContents()));
                    } else {
                        \App\Utils\Log::get()->error(sprintf('提现手续费失败：%s' ,$response->getStatusCode()));
                    }
                } catch (\Exception $e) {
                    \App\Utils\Log::get()->error(sprintf('提现手续费失败：%s' , $e->getMessage()));
                }
            }
        }

    }
}
