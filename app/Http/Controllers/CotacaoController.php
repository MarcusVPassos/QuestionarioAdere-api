<?php

namespace App\Http\Controllers;

use App\Events\RotacaoAtualizada;
use App\Models\Cotacao;
use App\Models\Questionario;
use App\Services\RotacaoVendedoresService;
use Illuminate\Http\Request;

class CotacaoController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $q = Cotacao::query()
            ->with(['vendedor:id,name','recepcionista:id,name','questionario:id,cotacao_id,status'])
            ->orderByDesc('id');

        // 🔒 Vendedor só vê as suas (não aplica para recepção explícita)
        if ($u->role === 'user' && !$request->boolean('somente_recepcao')) {
            $q->where('vendedor_id', $u->id);
        }

        // 🎯 Modo Recepção: somente "novo" e sem vendedor
        if ($request->boolean('somente_recepcao')) {
            $q->whereNull('vendedor_id')
            ->where('status', 'novo');
        } else {
            // Filtros padrão (aplicados apenas quando NÃO é recepção)
            if ($request->filled('status')) {
                $q->where('status', $request->string('status')->toString());
            }

            if ($request->filled('vendedor_id')) {
                $q->where('vendedor_id', (int) $request->vendedor_id);
            }
        }

        if ($request->filled('busca')) {
            $b = '%'.$request->string('busca')->toString().'%';
            $q->where(function($w) use ($b){
                $w->where('nome_razao','like',$b)
                ->orWhere('telefone','like',$b)
                ->orWhere('email','like',$b);
            });
        }

        return $q->paginate(10);
    }

    public function atribuir(Request $request, Cotacao $cotacao)
    {
        // somente recepcao/supervisor/diretoria
        if (!in_array($request->user()->role, ['recepcao','supervisor','diretoria'])) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        $data = $request->validate([
            'vendedor_id' => 'required|exists:users,id'
        ]);

        $cotacao->vendedor_id = $data['vendedor_id'];
        $cotacao->recepcionista_id = $request->user()->id;
        if ($cotacao->status === 'novo') $cotacao->status = 'em_atendimento';
        $cotacao->save();
        $cotacao->refresh(); // reforço, garante que o vendedor_id esteja visível
        $dadosRotacao = app(RotacaoVendedoresService::class)->calcular($cotacao); // 👈 ESSENCIAL

        event(new \App\Events\CotacaoAtualizada($cotacao));
        event(new RotacaoAtualizada($dadosRotacao));


        return response()->json(['message'=>'Atribuída com sucesso']);
    }

    public function atualizarStatus(Request $request, Cotacao $cotacao)
    {
        $usuario = $request->user();
        $pode = in_array($usuario->role, ['supervisor','diretoria'])
            || ($usuario->role === 'user' && $cotacao->vendedor_id === $usuario->id);
        if (!$pode) return response()->json(['error'=>'Sem permissão'], 403);

        // status geral
        $request->validate([
            'status' => 'required|in:em_atendimento,negociacao,recusado,convertido'
        ]);

        // detalhe opcional, mas controlado por lista (lado servidor)
        $validos = [
            'em_atendimento' => ['Aguardando contato','Em contato'],
            'negociacao'     => ['Análise de valores','Pendente de documentação','Previsão de fechar no próximo mês'],
            'recusado'       => ['Restrição no nome','Cliente sem resposta','Cliente mora em outro estado','Falta de documentação','Outro'],
            'convertido'     => [], // sem detalhe
        ];

        $status = $request->string('status')->toString();
        $detalhe = $request->string('status_detalhe')->toString();

        if ($status !== 'convertido') {
            // quando não convertido, se vier detalhe, precisa ser válido
            if ($detalhe !== '' && !in_array($detalhe, $validos[$status] ?? [], true)) {
                return response()->json(['error' => 'status_detalhe inválido para o status'], 422);
            }
        }

        // regra do "Outro"
        $motivo = $request->string('motivo_recusa')->toString();
        if ($status === 'recusado' && $detalhe === 'Outro' && $motivo === '') {
            return response()->json(['error' => 'motivo_recusa é obrigatório quando detalhe=Outro'], 422);
        }

        $cotacao->status = $status;
        $cotacao->status_detalhe = $detalhe ?: null;
        $cotacao->motivo_recusa = ($status === 'recusado') ? ($motivo ?: ($detalhe !== 'Outro' ? $detalhe : null)) : null;
        //       ↑ se recusado e não for "Outro", opcionalmente gravamos o próprio detalhe em motivo_recusa (fica documentado)
        $cotacao->save();

        event(new \App\Events\CotacaoAtualizada($cotacao));

        return response()->json(['message'=>'Status atualizado']);
    }

    public function converter(Request $request, Cotacao $cotacao)
    {
        $usuario = $request->user();
        $pode = in_array($usuario->role, ['supervisor','diretoria'])
             || ($usuario->role === 'user' && $cotacao->vendedor_id === $usuario->id);

        if (!$pode) return response()->json(['error'=>'Sem permissão'], 403);

        // cria Questionario pré-preenchido com o mínimo necessário
        $dados = [
            'tipo'          => $cotacao->pessoa,               // 'pf' | 'pj'
            'nome'          => $cotacao->nome_razao,
            'telefone'      => $cotacao->telefone,
            'email'         => $cotacao->email,
            'origem'        => $cotacao->origem,
            'mensagem'      => $cotacao->mensagem,
            'tipo_veiculo'  => $cotacao->tipo_veiculo,
            'modelo_veiculo'=> $cotacao->modelo_veiculo,
            'data_precisa'  => optional($cotacao->data_precisa)->format('Y-m-d'),
            'tempo_locacao' => $cotacao->tempo_locacao,
        ];

        $questionario = Questionario::create([
            'tipo'   => $cotacao->pessoa,           // seu model já aceita 'pf'|'pj'
            'dados'  => $dados,
            'status' => 'pendente',
            'user_id'=> $usuario->id,               // quem está convertendo (ajuste se quiser outro dono)
        ]);

        $cotacao->status = 'convertido';
        $cotacao->save();

        event(new \App\Events\CotacaoAtualizada($cotacao /*, $questionario */));

        return response()->json([
            'message' => 'Convertido em questionário',
            'questionario_id' => $questionario->id
        ], 201);
    }

    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        return \App\Models\Cotacao::query()
            ->select('id', 'nome_razao', 'status', 'status_detalhe')
            ->where('status', '!=', 'convertido')
            ->whereDoesntHave('questionario')
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('nome_razao', 'like', "%{$q}%")
                    ->orWhere('id', $q);
                });
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }
}
