<?php

namespace App\Http\Controllers;

use App\Events\CotacaoAtribuida;
use App\Events\CotacaoAtualizada;
use App\Events\CotacaoConvertida;
use App\Events\RotacaoAtualizada;
use App\Models\Cotacao;
use App\Models\Questionario;
use App\Services\RotacaoVendedoresService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CotacaoController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $q = Cotacao::query()
            ->with(['vendedor:id,name','recepcionista:id,name','questionario:id,cotacao_id,status'])
            ->orderByDesc('created_at'); // 👈 ordena por data de criação

        // 🔒 Vendedor só vê as suas (não aplica para recepção explícita)
        if ($u->role === 'user' && !$request->boolean('somente_recepcao')) {
            $q->where('vendedor_id', $u->id);
        }

        // 🎯 Modo Recepção
        if ($request->boolean('somente_recepcao')) {
            $q->whereNull('vendedor_id')->where('status', 'novo');
        } else {
            if ($request->filled('status')) {
                $q->where('status', $request->string('status'));
            }
            if ($request->filled('vendedor_id')) {
                $q->where('vendedor_id', (int) $request->vendedor_id);
            }
        }

        // 🔎 Busca
        if ($request->filled('busca')) {
            $b = '%'.$request->string('busca').'%';
            $q->where(function($w) use ($b){
                $w->where('nome_razao','like',$b)
                ->orWhere('telefone','like',$b)
                ->orWhere('email','like',$b);
            });
        }

        // 📅 Filtros por mês/dia
        $ano  = (int) $request->query('ano', 0);
        $mes  = (int) $request->query('mes', 0);
        $dia  = (string) $request->query('dia', ''); // YYYY-MM-DD

        // dia tem prioridade
        if ($dia !== '') {
            $q->whereDate('created_at', $dia);
        } elseif ($ano > 0 && $mes > 0) {
            // intervalo mês
            $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
            $fim    = (clone $inicio)->endOfMonth();
            $q->whereBetween('created_at', [$inicio, $fim]);
        } elseif ($ano > 0) {
            $inicio = Carbon::createFromDate($ano, 1, 1)->startOfDay();
            $fim    = (clone $inicio)->endOfYear();
            $q->whereBetween('created_at', [$inicio, $fim]);
        }

        // ♾️ Cursor pagination (10 por vez)
        $perPage = (int) $request->query('per_page', 10);
        $results = $q->cursorPaginate($perPage)->withQueryString();

        // resposta amigável ao frontend para infinite scroll
        return response()->json([
            'data'        => $results->items(),
            'next_cursor' => $results->nextCursor()?->encode(), // string ou null
            'has_more'    => $results->hasMorePages(),
        ]);
    }

    public function atribuir(Request $request, Cotacao $cotacao)
    {
        // somente recepcao/supervisor/diretoria
        if (!in_array($request->user()->role, ['recepcao','supervisor','diretoria'])) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        $data = $request->validate([
            'vendedor_id' => 'required|exists:users,id',
            'sem_rotacao'   => 'sometimes|boolean'
        ]);

        $cotacao->vendedor_id = $data['vendedor_id'];
        $cotacao->recepcionista_id = $request->user()->id;

        if ($cotacao->status === 'novo') $cotacao->status = 'em_atendimento';

        $cotacao->save();
        $cotacao->refresh(); // reforço, garante que o vendedor_id esteja visível

        if (empty($data['sem_rotacao'])) {
            $dadosRotacao = app(RotacaoVendedoresService::class)->calcular($cotacao);
            event(new RotacaoAtualizada($dadosRotacao));
        }

        event(new CotacaoAtribuida($cotacao));


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

        event(new CotacaoAtualizada($cotacao));

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

        event(new CotacaoConvertida($cotacao));

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

    public function totalHoje()
    {
        $hoje = Carbon::today()->toDateString();

        $total = Cotacao::whereDate('created_at', $hoje)->count();

        return response()->json([
            'data'  => $hoje,
            'total' => $total,
        ]);
    }
}
