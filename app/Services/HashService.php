<?php


namespace App\Service;

use Jenssegers\Optimus\Optimus;

class HashService
{

    protected $optimus;

    public function __construct()
    {
        $this->optimus = new Optimus(config('optimus.prime', 2079446857), config('optimus.inverse', 280414969), config('optimus.random', 1831128969));
    }

    public function encode($value) {
        return $this->optimus->encode($value);
    }

    public function decode($value) {
        return $this->optimus->decode($value);
    }
}