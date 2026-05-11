<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Illuminate\Contracts\Support\Arrayable;

class ManticoreVector implements Arrayable
{
    protected array $vector;

    public function __construct(...$vector)
    {
        $this->vector = array_map('floatval', $vector);
    }

    public function toSql(): string
    {
        return '('.implode(',', $this->vector).')';
    }

    public function toArray()
    {
        return $this->vector;
    }
}