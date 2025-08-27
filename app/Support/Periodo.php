<?php

namespace App\Support;

use Carbon\Carbon;

final class Periodo
{
    public static function mes(int $ano, int $mes): array
    {
        $ini = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fim = (clone $ini)->endOfMonth();
        return [$ini, $fim];
    }
}
