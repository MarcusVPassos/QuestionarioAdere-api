<?php

namespace Database\Seeders;

use App\Models\Questionario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class QuestionarioSeeder extends Seeder
{
    public function run(): void
    {
        // Cria 5 perfis fixos com role
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

        $tempos = [
            'Diário',
            'Mensal',
            'Anual',
            'Assinatura Anual',
            'plano mensal',
            'plano diário',
            'assinatura',
            '1 mês',
            '1 dia',
            '1 ano',
            'mensalidade',
            'assinatura mensal',
            'mensal com desconto',
            'diário promocional',
            'plano anual',
        ];

        $startDate = Carbon::create(2025, 5, 1);
        $total = 100;
        $porMes = 25;

        for ($i = 0; $i < $total; $i++) {
            $mesOffset = intdiv($i, $porMes);
            $dia = ($i % $porMes) + 1;
            $createdAt = $startDate->copy()->addMonths($mesOffset)->day(min($dia, 28));

            Questionario::create([
                'tipo' => fake()->randomElement(['pf', 'pj']),
                'dados' => [
                    'nome' => fake()->name(),
                    'cpf' => fake()->cpf(false),
                    'email' => fake()->safeEmail(),
                    'telefone' => fake()->phoneNumber(),
                    'endereco' => fake()->address(),
                    'observacoes' => fake()->sentence(),
                    'tempo' => fake()->randomElement($tempos),
                ],
                'status' => fake()->randomElement(['pendente', 'aprovado', 'negado', 'correcao']),
                'motivo_negativa' => fake()->optional()->sentence(),
                'comentario_correcao' => fake()->optional()->sentence(),
                'user_id' => $usuarios->random()->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
