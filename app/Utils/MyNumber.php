<?php

namespace App\Utils;

use App\Services\HashService;
use Brick\Math\BigDecimal;
use Hyperf\Utils\ApplicationContext;

class MyNumber
{
    public static function formatSoke($amount)
    {
        return BigDecimal::of($amount)->toScale(6);
    }

}
