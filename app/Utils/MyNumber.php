<?php

namespace App\Utils;

use App\Services\HashService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Hyperf\Utils\ApplicationContext;

class MyNumber
{
    public static function formatCpu($amount)
    {
        return BigDecimal::of($amount)->toScale(6, RoundingMode::DOWN);
    }

    public static function formatPower($amount)
    {
        return BigDecimal::of($amount)->toScale(4, RoundingMode::DOWN);
    }

}
