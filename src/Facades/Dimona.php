<?php

namespace Hyperlab\Dimona\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Hyperlab\Dimona\DimonaManager client(?string $clientId = null)
 * @method static void declare(\Carbon\CarbonPeriodImmutable $period, \Illuminate\Support\Collection $employments)
 *
 * @see \Hyperlab\Dimona\DimonaManager
 */
class Dimona extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return self::class;
    }
}
