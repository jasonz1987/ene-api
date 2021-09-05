<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ProfitLog;
use App\Services\QueueService;
use App\Utils\Log;
use Brick\Math\RoundingMode;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;

/**
 * @Command
 */
class DaoDraw extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('dao:draw');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('dao发奖接口');
    }

    public function handle()
    {
        $clientFactory  = ApplicationContext::getContainer()->get(ClientFactory::class);
        $queueService  = ApplicationContext::getContainer()->get(QueueService::class);

        $options = [];
        // $client 为协程化的 GuzzleHttp\Client 对象
        $client = $clientFactory->create($options);

        $url = sprintf('http://localhost:3001');

        $response = $client->request('GET', $url);

        $code =  $response->getStatusCode(); // 200

        if ($code == 200) {
            $body =  $response->getBody()->getContents();

            var_dump($body);

            $body = json_decode($body, true);

            if ($body['code'] == 200) {
                Log::get()->info("DAO奖励发放成功");

            } else {
                Log::get()->error("DAO奖励发放失败：%s", $body['message']);
            }

        } else {
            Log::get()->error("DAO奖励发放失败：接口错误%s", $code);
        }

    }
}
