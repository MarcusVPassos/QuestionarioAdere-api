<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ComissaoService extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * @return array{percentual: float, base: float, valor: float}
     */
    public static function calcular(?string $tipoVenda, ?float $valorVenda, ?float $valorMensalidade): array
    {
        if (!in_array($tipoVenda, ['diario','mensal','assinatura'], true)) {
            return ['percentual'=>0.0,'base'=>0.0,'valor'=>0.0];
        }

        if (in_array($tipoVenda, ['diario','mensal'], true)) {
            $percentual = 0.05;
            $base = (float) ($valorVenda ?? 0);
        } else { // assinatura
            $percentual = 0.08;
            $base = (float) ($valorMensalidade ?? 0);
        }

        $valor = round($base * $percentual, 2);

        return ['percentual'=>$percentual, 'base'=>$base, 'valor'=>$valor];
    }
}
