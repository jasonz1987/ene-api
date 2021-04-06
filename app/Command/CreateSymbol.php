<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ContractSymbol;
use App\Model\Symbol;
use App\Service\SymbolService;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Guzzle\ClientFactory;
use Psr\Container\ContainerInterface;

/**
 * @Command
 */
class CreateSymbol extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('create:symbol');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('获取交易对');
    }

    public function handle()
    {
        $clientFactory = $this->container->get(ClientFactory::class);
        $client = $clientFactory->create([]);

        $url = 'https://' . env('HUOBI_API_HOST') . '/v1/common/symbols';

        $this->line($url);

        $response = $client->request('GET', $url, [
        ]);

        if ($response->getStatusCode() == 200) {

            $content = $response->getBody()->getContents();

            $result = json_decode($content, TRUE);


            if ($result && isset($result['data'])) {
                $data = $result['data'];

                $symbols = [];

                foreach ($data as $k => $v) {
                    if ($v['quote-currency'] == 'usdt') {
                        $this->line("更新交易对:" . $v['symbol']);
                        $symbols[] = [
                            'symbol'     => $v['symbol'],
                            'status'     => 0,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ];
                    }
                }

                if ($symbols) {
                    ContractSymbol::insert($symbols);
                }
            } else {
                $this->error("请求失败");
            }
        }
    }
}
