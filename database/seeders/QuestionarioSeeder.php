<?php

namespace Database\Seeders;

use App\Models\Cotacao;
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
            ['name' => 'recepcao', 'role' => 'recepcao'],
            ['name' => 'vendedor1', 'role' => 'user'],
            ['name' => 'vendedor2', 'role' => 'user'],
            ['name' => 'vendedor3', 'role' => 'user'],
        ])->map(fn($u) => User::firstOrCreate(
            ['name' => $u['name']],
            ['password' => bcrypt('12345678'), 'role' => $u['role']]
        ));

        $tiposVenda = ['diario', 'mensal', 'assinatura'];
        $startDate = Carbon::create(2025, 5, 1);
        $endDate = Carbon::create(2025, 8, 31);

        $vendedores = $usuarios->where('role', 'user')->values();
        $recepcionista = $usuarios->where('role', 'recepcao')->first();

        $dias = $startDate->diffInDaysFiltered(fn ($d) => $d->isWeekday(), $endDate);

        for ($offset = 0; $offset <= $dias; $offset++) {
            $data = $startDate->copy()->addDays($offset);

            // Gera de 2 a 4 cotações no mesmo dia
            $quantidadeCotacoes = fake()->numberBetween(2, 4);

            for ($j = 0; $j < $quantidadeCotacoes; $j++) {
                $tipoVenda = fake()->randomElement($tiposVenda);

                if ($tipoVenda === 'diario') {
                    $valorVenda = fake()->randomFloat(2, 80, 300);
                    $valorMensalidade = null;
                    $valorVendaTotal = $valorVenda;
                } elseif ($tipoVenda === 'mensal') {
                    $valorMensalidade = fake()->randomFloat(2, 300, 1200);
                    $valorVenda = null;
                    $valorVendaTotal = $valorMensalidade;
                } else {
                    $valorMensalidade = fake()->randomFloat(2, 200, 800);
                    $valorVenda = null;
                    $valorVendaTotal = $valorMensalidade * 12;
                }

                $calc = ComissaoService::calcular($tipoVenda, $valorVenda, $valorMensalidade);

                // 20% das cotações ficam sem vendedor (em recepção)
                $vendedor = fake()->boolean(80) ? $vendedores->random() : null;

                // cria a cotação
                $cotacao = Cotacao::create([
                    'pessoa' => fake()->randomElement(['pf', 'pj']),
                    'nome_razao' => fake()->name(),
                    'cpf' => fake()->cpf(false),
                    'cnpj' => fake()->cnpj(false),
                    'telefone' => fake()->phoneNumber(),
                    'email' => fake()->safeEmail(),
                    'origem' => fake()->word(),
                    'mensagem' => fake()->sentence(),
                    'tipo_veiculo' => fake()->word(),
                    'modelo_veiculo' => fake()->word(),
                    'data_precisa' => $data->copy()->addDays(3),
                    'tempo_locacao' => $tipoVenda,
                    'tipo_venda' => $tipoVenda,
                    'status' => 'novo',
                    'status_detalhe' => null,
                    'vendedor_id' => $vendedor?->id,
                    'recepcionista_id' => $recepcionista?->id,
                    'canal_raw' => [],
                    'observacoes' => fake()->sentence(),
                    'created_at' => $data,
                    'updated_at' => $data,
                ]);

                // 50% das cotações com vendedor viram questionário
                $criarQuestionario = $vendedor && fake()->boolean(50);

                if ($criarQuestionario) {
                    Questionario::create([
                        'tipo' => $cotacao->pessoa,
                        'dados' => [
                            'nome' => $cotacao->nome_razao,
                            'cpf' => $cotacao->cpf,
                            'cnpj' => $cotacao->cnpj,
                            'email' => $cotacao->email,
                            'telefone' => $cotacao->telefone,
                            'endereco' => fake()->address(),
                            'observacoes' => fake()->sentence(),
                        ],
                        'status' => fake()->randomElement(['pendente', 'aprovado', 'negado', 'correcao']),
                        'user_id' => $vendedor->id,
                        'tipo_venda' => $tipoVenda,
                        'valor_venda' => $valorVenda,
                        'valor_venda_total' => $valorVendaTotal,
                        'valor_mensalidade' => $valorMensalidade,
                        'percentual_comissao' => $calc['percentual'],
                        'valor_comissao_calculado' => $calc['valor'],
                        'motivo_negativa' => fake()->optional()->sentence(),
                        'comentario_correcao' => fake()->optional()->sentence(),
                        'cotacao_id' => $cotacao->id,
                        'created_at' => $data,
                        'updated_at' => $data,
                    ]);

                    // atualiza cotação como convertida
                    $cotacao->update([
                        'status' => 'convertido',
                        'status_detalhe' => 'Aprovado via questionário',
                    ]);
                }
            }
        }
    }
}