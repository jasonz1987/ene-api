<?php


namespace App\Services;

use App\Model\ContractPosition;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Redis\Redis;
use Jenssegers\Optimus\Optimus;
use Hyperf\Di\Annotation\Inject;

class ContractService
{
    /**
     * @Inject()
     * @var ContainerInterface
     */
    private $container;

    /**
     * 获取最新价格
     *
     * @param $code
     * @return mixed
     */
    public function getIndexLastPrice($code)
    {
        return $this->container->get(Redis::class)->hGet('index.prices', $code);
    }

    /**
     * 获取开盘价
     *
     * @param $value
     * @return mixed
     */
    public function getIndexOpenPrice($code)
    {
        $data = $this->getIndexTodayKline($code);

        if ($data) {
            return $data['open'];
        }

        return null;
    }

    /**
     * 获取今日K线
     *
     * @param $code
     * @return mixed|null
     */
    public function getIndexTodayKline($code)
    {
        $data = $this->container->get(Redis::class)->hGet('index.kline.' . $code . '.1day', strval(strtotime(date('Y-m-d' . ' 00:00:00'))));

        if ($data) {
            return unserialize($data);
        }

        return null;
    }

    /**
     * 获取涨跌幅
     *
     * @param $code
     * @return string|null
     */
    public function getIndexQuoteChange($code)
    {
        $open_price = $this->getIndexOpenPrice($code);
        $last_price = $this->getIndexLastPrice($code);

        if ($open_price && $last_price) {
            return strval(round(($last_price - $open_price) / $open_price, 4));
        }

        return null;
    }

    public function addIndexOrders($index, $price, $direction, $id)
    {
        return $this->container->get(Redis::class)->zAdd('index.orders:' . $index . ':' . $direction,  strval($price), $id);
    }

    /**
     * 获取委托记录
     *
     * @param $index
     * @param $price
     * @param $direction
     * @return array
     */
    public function getIndexOrders($index, $price, $direction)
    {
        if ($direction == 'buy') {
            return $this->container->get(Redis::class)->zRangeByScore('index.orders:' . $index . ':' . $direction, strval($price-5), strval($price));
        } else if ($direction == 'sell') {
            return $this->container->get(Redis::class)->zRangeByScore('index.orders:' . $index . ':' . $direction, strval($price), strval($price+5));
        }

        return [];
    }

    public function removeIndexOrder($index, $direction, $id)
    {
        return $this->container->get(Redis::class)->zRem('index.orders:' . $index . ':' . $direction, $id);
    }

    public function updatePosition($order)
    {
        // 判断是否开仓
        $position = ContractPosition::where('index_id', '=', $order->index->id)
            ->where('user_id', '=', $order->user_id)
            ->where('status', '=', 1)
            ->first();

        if (!$position) {
            // 创建仓位
            $position = new ContractPosition();
            $position->index_id = $order->index->id;
            $position->direction = $order->direction;
            $position->user_id = $order->user_id;
            $position->open_price = $order->price;
            $position->position_volume = $order->volume;
            $position->position_amount = $order->amount;
            $position->lever = $order->index->lever;
            $position->save();
        } else {
            // 更新仓位
            $position->open_price = BigDecimal::of($position->open_price)->multipliedBy($position->position_volume)->plus(BigDecimal::of($order->price)->multipliedBy($order->volume))->dividedBy($position->position_volume+$order->volume,$order->index->price_decimal, RoundingMode::DOWN);
            $position->position_volume += $order->volume;
            $new_amount = BigDecimal::of($position->position_amount)->plus($order->amount);
            $position->position_amount = $new_amount;

            $position->save();
        }

        // 更新ORDER记录
        $order->position_id = $position->id;
        $order->status = 1;
        $order->trade_amount = $order->amount;
        $order->trade_volume = $order->volume;
        $order->save();
    }

    /**
     * 获取强平价格
     */
    public function getLiquidationPrice($position)
    {
        // 获取当前最新价格
        $last_price = $position->open_price;
//
        if ($last_price) {

            $balance = BigDecimal::of($position->user->balance)->dividedBy($position->lever, $position->index->price_decimal, RoundingMode::UP);

            $volume = BigDecimal::of($position->index->size)->multipliedBy($position->position_volume);

            // 维持保证金
            $mm = $volume->multipliedBy($position->open_price)->multipliedBy($position->index->mm_rate);

            $price = $balance->plus($position->position_amount)->minus($mm)->dividedBy($volume, $position->index->price_decimal, RoundingMode::UP);

            if ($position->direction == 'buy') {
                $liquidation_price = BigDecimal::of($last_price)->minus($price);
            } else {
                $liquidation_price = BigDecimal::of($last_price)->plus($price);
            }

            return $liquidation_price->toScale(4, RoundingMode::DOWN);
        }

        return null;
    }

    /**
     * 获取未实现收益
     *
     * @param $position
     * @return BigDecimal|\Brick\Math\BigNumber|null
     */
    public function getUnrealProfit($position)
    {
        // 获取当前最新价格
        $last_price = $this->getIndexLastPrice($position->index->code);
        $profit = BigDecimal::zero();

        if ($last_price) {
            if ($position->direction == 'buy') {
                $profit = BigDecimal::of($last_price)->minus($position->open_price);
            } else {
                $profit = BigDecimal::of($position->open_price)->minus($last_price);
            }

            $profit = $profit->multipliedBy($position->position_volume)->multipliedBy($position->index->size)->toScale(6);
        }

        return $profit;
    }

    public function getProfitRate($position)
    {
        $profit = $this->getUnrealProfit($position);

        $rate = $profit->dividedBy($position->amount, 4, RoundingMode::UP);

        return $rate;
    }

    public function updatePositionProfit($uid, $index, $profit, $balance)
    {
         $this->container->get(Redis::class)->hSet('index.positions:' . $uid, $index, $profit);
    }

    public function removePositionQueue($uid, $index) {
        $this->container->get(Redis::class)->hDel('index.positions:' . $uid, $index);
    }

    public function addOrderLimit($uid) {
        return $this->container->get(Redis::class)->set('index.order.limit:' . $uid, time(), ['nx', 'ex' => 5]);
    }

    public function addOrderLock($id) {
        return $this->container->get(Redis::class)->set('index.order.lock:' . $id, time(), ['nx', 'ex' => 5]);
    }

    public function getTotalMarket() {
        return $this->container->get(Redis::class)->get('soke_database_market_pledge');
    }

    public function incrTotalMarket($num) {
        return $this->container->get(Redis::class)->incrByFloat('soke_database_market_pledge', $num);
    }

}