<?php

namespace App\Utils;

use App\Service\HashService;
use Hyperf\Utils\ApplicationContext;

class HashId
{
    public static function encode($id)
    {
        return ApplicationContext::getContainer()->get(HashService::class)->encode($id);
    }

    public static function decode($id)
    {
        return ApplicationContext::getContainer()->get(HashService::class)->decode($id);
    }
}
