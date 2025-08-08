<?php

namespace Database\Seeders;

use App\Models\Questionario;
use App\Models\User;
use App\Providers\ComissaoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class QuestionarioSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = collect([
            ['name' => 'diretoria', 'role' => 'diretoria'],
            ['name' => 'supervisor', 'role' => 'supervisor'],
            ['name' => 'vendedor1', 'role' => 'user'],
            ['name' => 'vendedor2', 'role' => 'user'],
            ['name' => 'vendedor3', 'role' => 'user'],
        ])->map(fn($u) => User::firstOrCreate(
            ['name' => $u['name']],
            ['password' => bcrypt('12345678'), 'role' => $u['role']]
        ));

        $tiposVenda = ['diario', 'mensal', 'assinatura'];

        $startDate = Carbon::create(2025, 5, 1);
        $total = 100;
        $porMes = 25;

        for ($i = 0; $i < $total; $i++) {
            $mesOffset = intdiv($i, $porMes);
            $dia = ($i % $porMes) + 1;
            $createdAt = $startDate->copy()->addMonths($mesOffset)->day(min($dia, 28));

            $tipoVenda = fake()->randomElement($tiposVenda);

            // valores simulados
            if ($tipoVenda === 'diario') {
                $valorVenda = fake()->randomFloat(2, 80, 300);
                $valorMensalidade = null;
                $valorVendaTotal = $valorVenda;
            } elseif ($tipoVenda === 'mensal') {
                $valorMensalidade = fake()->randomFloat(2, 300, 1200);
                $valorVenda = null;
                $valorVendaTotal = $valorMensalidade;
            } else { // assinatura
                $valorMensalidade = fake()->randomFloat(2, 200, 800);
                $valorVenda = null;
                $valorVendaTotal = $valorMensalidade * 12;
            }

            // cálculo real de comissão
            $calc = ComissaoService::calcular(
                $tipoVenda,
                $valorVenda,
                $valorMensalidade
            );

            Questionario::create([
                'tipo' => fake()->randomElement(['pf', 'pj']),
                'dados' => [
                    'nome' => fake()->name(),
                    'cpf' => fake()->cpf(false),
                    'email' => fake()->safeEmail(),
                    'telefone' => fake()->phoneNumber(),
                    'endereco' => fake()->address(),
                    'observacoes' => fake()->sentence(),
                ],
                'status' => fake()->randomElement(['pendente', 'aprovado', 'negado', 'correcao']),
                'user_id' => $usuarios->random()->id,
                'tipo_venda' => $tipoVenda,
                'valor_venda' => $valorVenda,
                'valor_venda_total' => $valorVendaTotal,
                'valor_mensalidade' => $valorMensalidade,
                'percentual_comissao' => $calc['percentual'],
                'valor_comissao_calculado' => $calc['valor'],
                'motivo_negativa' => fake()->optional()->sentence(),
                'comentario_correcao' => fake()->optional()->sentence(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
