<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\CotacaoGoogle;
use App\Events\CotacoesAtualizadas;

class CotacoesLoop extends Command
{
    protected $signature = 'cotacoes:loop';
    protected $description = 'Importa cotações do Apps Script a cada 30 segundos e atualiza o banco';

    public function handle()
    {
        $this->info('🟢 Iniciando loop de importação de cotações...');

        while (true) {
            $mes = now()->month;
            $ano = now()->year;

            $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
            $params = [
                'action' => 'resumoCotacoesPorDia',
                'mes' => $mes,
                'ano' => $ano,
            ];

            try {
                $resposta = Http::timeout(120)->get($url, $params);

                if (!$resposta->successful()) {
                    $this->error('🔴 Falha ao acessar Apps Script');
                } else {
                    $dados = $resposta->json();

                    foreach ($dados as $item) {
                        $data = Carbon::createFromDate($ano, $mes, $item['dia'])->format('Y-m-d');

                        CotacaoGoogle::updateOrCreate(
                            ['data' => $data],
                            ['total' => $item['total']]
                        );
                    }

                    $this->info('✅ Importação concluída - ' . now());
                    event(new CotacoesAtualizadas());
                }

            } catch (\Throwable $e) {
                $this->error('⚠️ Erro: ' . $e->getMessage());
            }

            sleep(120);
        }
    }
}
