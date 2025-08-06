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
        // Gera 5 usuários aleatórios se não existirem
        $usuarios = User::take(5)->get();

        if ($usuarios->count() < 5) {
            $usuarios = User::factory()
                ->count(5)
                ->sequence(
                    ['name' => 'diretoria', 'role' => 'diretoria'],
                    ['name' => 'supervisor', 'role' => 'supervisor'],
                    ['name' => 'vendedor1', 'role' => 'user'],
                    ['name' => 'vendedor2', 'role' => 'user'],
                    ['name' => 'vendedor3', 'role' => 'user'],
                )
                ->create()
                ->merge($usuarios); // inclui os já existentes (se houver)
        }

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
                ],
                'status' => fake()->randomElement(['pendente', 'aprovado', 'negado', 'correcao']),
                'motivo_negativa' => fake()->optional()->sentence(),
                'comentario_correcao' => fake()->optional()->sentence(),
                'user_id' => $usuarios->random()->id, // usuário aleatório
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
