<?php

namespace Kodus\Cache\Tests;

use Kodus\Cache\DatabaseCache;

class TestableDatabaseCache extends DatabaseCache
{
    protected $time_travel = 0;

    protected function getTime()
    {
        return parent::getTime() + $this->time_travel;
    }

    public function timeTravel(int $time)
    {
        $this->time_travel += $time;
    }
}
