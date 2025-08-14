<?php

namespace App\Http\Controllers;

use App\Events\NovoQuestionarioCriado;
use App\Http\Requests\StoreQuestionarioRequest;
use App\Http\Requests\UpdateQuestionarioRequest;
use App\Models\Cotacao;
use Illuminate\Http\Request;
use App\Models\Questionario;
use App\Models\Documento;
use App\Providers\ComissaoService;
use App\Services\SyncCotacaoStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;


class QuestionarioController extends Controller
{

public function index(Request $request)
{
    $user = $request->user();
    $query = Questionario::with(['documentos', 'user'])->orderByDesc('created_at');

    if ($user->role === 'user') {
        // Usuário comum só vê os próprios com status específico
        if ($request->input('status') === 'correcao') {
            $query->where('user_id', $user->id)
                ->where('status', 'correcao');
        } else {
            $query->where('user_id', $user->id)
                ->whereIn('status', ['aprovado', 'negado']);
        }
    } else {
        // Supervisor e diretoria veem tudo, sem filtro por user_id
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'correcao'); // exceto se status não for definido
        }

        // 🔍 Filtro por user_id (apenas para supervisores e diretoria)
        if ($request->filled('so_user')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->so_user . '%');
            });
        }
    }

    if ($request->filled('tipo')) {
        $query->where('tipo', $request->tipo);
    }

    if ($request->filled('ano')) {
        $query->whereYear('created_at', $request->ano);
    }

    if ($request->filled('mes')) {
        $query->whereMonth('created_at', $request->mes);
    }

    if ($request->filled('nome')) {
        $query->where(function ($q) use ($request) {
            $q->where('dados->nome', 'like', '%' . $request->nome . '%')
              ->orWhere('dados->razao', 'like', '%' . $request->nome . '%');
        });
    }

    if ($request->filled('protocolo')) {
        $query->where('id', $request->protocolo);
    }

    return $query->paginate(10);
}


    public function store(StoreQuestionarioRequest $request)
    {
        // Tudo já foi validado no FormRequest; 'dados' já é array
        $validated = $request->validated();
        $dados = $validated['dados'];

        if (!in_array(($dados['tipo'] ?? null), ['pf', 'pj'])) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        // Cálculo de comissão (fonte da verdade)
        $calc = ComissaoService::calcular(
            $request->input('tipo_venda'),
            $request->float('valor_venda'),
            $request->float('valor_mensalidade')
        );

        $questionario = Questionario::create([
            'tipo'                     => $dados['tipo'],
            'dados'                    => $dados,
            'cotacao_id'               => $validated['cotacao_id'] ?? null,
            'status'                   => 'pendente',
            'user_id'                  => $request->user()->id,
            'tipo_venda'               => $request->input('tipo_venda'),
            'valor_venda'              => $request->input('valor_venda'),
            'valor_venda_total'        => $request->input('valor_venda_total'),
            'valor_mensalidade'        => $request->input('valor_mensalidade'),
            'percentual_comissao'      => $calc['percentual'],
            'valor_comissao_calculado' => $calc['valor'],
        ]);

        // documentos[] no frontend vira 'documentos' aqui
        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos', []) as $file) {
                $path = $file->store('documentos', 'public');

                Documento::create([
                    'questionario_id' => $questionario->id,
                    'nome_original'   => $file->getClientOriginalName(),
                    'caminho'         => $path,
                    'mime_type'       => $file->getClientMimeType(),
                ]);
            }
        }

        // Converter cotação (se enviada)
        if (!empty($validated['cotacao_id'])) {
            // o questionário recém-criado está 'pendente' por padrão (como já está no seu código)
            SyncCotacaoStatusService::syncFromQuestionario($questionario);
        }

        $questionario->load(['documentos', 'cotacao']);
        event(new NovoQuestionarioCriado($questionario));
        Cache::flush();

        return response()->json([
            'message'      => 'Questionário enviado com sucesso',
            'protocolo'    => $questionario->id,
            'questionario' => $questionario,
        ], 201);
    }

    public function downloadDocumento(Request $request, $filename)
    {
        $path = 'api/private/documentos/' . $filename;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        return Storage::download($path);
    }
    public function atualizarStatus(Request $request, $id)
    {
        $questionario = Questionario::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pendente,aprovado,negado,correcao',
            'motivo_negativa' => 'nullable|string',
            'comentario_correcao' => 'nullable|string'
        ]);

        $novoStatus = $request->status;
        $questionario->status = $novoStatus;

        if ($novoStatus === 'negado') {
            if (!$request->motivo_negativa) {
                return response()->json(['message' => 'Motivo da recusa é obrigatório'], 422);
            }
            $questionario->motivo_negativa = $request->motivo_negativa;
            $questionario->comentario_correcao = null;
        } elseif ($novoStatus === 'correcao') {
            $questionario->comentario_correcao = $request->comentario_correcao ?? '';
            $questionario->motivo_negativa = null;
        } else {
            // Limpa campos ao aprovar ou voltar para pendente
            $questionario->motivo_negativa = null;

            // Manter o comentário de correção se voltando para pendente
            if ($novoStatus !== 'pendente') {
                $questionario->comentario_correcao = null;
            }
        }

        $questionario->save();

        SyncCotacaoStatusService::syncFromQuestionario($questionario);

        event(new NovoQuestionarioCriado($questionario));
        Cache::flush();

        return response()->json([
            'message' => 'Status atualizado com sucesso.',
            'status' => $questionario->status,
            'comentario' => $questionario->comentario_correcao,
            'motivo_negativa' => $questionario->motivo_negativa
        ]);
    }

    public function emCorrecao()
    {
        return Questionario::with('documentos') // se precisar dos arquivos
            ->where('status', 'correcao')
            ->get();
    }

    public function update(UpdateQuestionarioRequest $request, $id)
    {
        $validated = $request->validated();  // 'dados' já é array
        $dados = $validated['dados'];

        if (!in_array(($dados['tipo'] ?? ''), ['pf', 'pj'])) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        $questionario = Questionario::findOrFail($id);
        $questionario->dados = $dados;
        $questionario->status = 'pendente';
        $questionario->comentario_correcao = null;
        $questionario->motivo_negativa = null;

        // Recalcular comissão
        $calc = ComissaoService::calcular(
            $request->input('tipo_venda'),
            $request->float('valor_venda'),
            $request->float('valor_mensalidade')
        );

        $questionario->tipo_venda               = $request->input('tipo_venda', $questionario->tipo_venda);
        $questionario->valor_venda              = $request->input('valor_venda', $questionario->valor_venda);
        $questionario->valor_venda_total        = $request->input('valor_venda_total', $questionario->valor_venda_total);
        $questionario->valor_mensalidade        = $request->input('valor_mensalidade', $questionario->valor_mensalidade);
        $questionario->percentual_comissao      = $calc['percentual'];
        $questionario->valor_comissao_calculado = $calc['valor'];
        $questionario->save();

        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos', []) as $file) {
                $path = $file->store('documentos', 'public');

                Documento::create([
                    'questionario_id' => $questionario->id,
                    'nome_original'   => $file->getClientOriginalName(),
                    'caminho'         => $path,
                    'mime_type'       => $file->getClientMimeType(),
                ]);
            }
        }

        $questionario->refresh()->load('documentos');
        event(new NovoQuestionarioCriado($questionario));
        Cache::flush();

        return response()->json([
            'message' => 'Questionário atualizado com sucesso.',
            'status'  => 'pendente',
        ]);
    }


    public function previewComissao(Request $request)
    {
        $request->validate([
            'tipo_venda' => 'required|in:diario,mensal,assinatura',
            'valor_venda' => 'nullable|numeric|min:0',
            'valor_venda_total' => 'nullable|numeric|min:0',
            'valor_mensalidade' => 'nullable|numeric|min:0'
        ]);

        $calc = ComissaoService::calcular(
            $request->input('tipo_venda'),
            $request->float('valor_venda'),
            $request->float('valor_mensalidade')
        );

        return response()->json($calc);
    }

    public function anosEMesesDisponiveis()
    {
        return Questionario::selectRaw('YEAR(created_at) as ano, MONTH(created_at) as mes')
            ->distinct()
            ->orderByDesc('ano')
            ->orderByDesc('mes')
            ->get();
    }

    private function gerarCacheKey(Request $request): string
    {
        $filtros = [
            'status' => $request->input('status'),
            'tipo' => $request->input('tipo'),
            'ano' => $request->input('ano'),
            'mes' => $request->input('mes'),
            'nome' => $request->input('nome'),
            'protocolo' => $request->input('protocolo'),
            'so_user' => $request->input('so_user'),
            'pagina' => $request->input('page', 1),
        ];

        return 'questionarios:' . md5(json_encode($filtros));
    }

    public function buscarCotacoes(Request $request)
    {
        $busca = $request->input('busca', '');
        
        return Cotacao::where('status', '!=', 'convertido')
            ->where(function($q) use ($busca) {
                $q->where('nome_cliente', 'like', "%{$busca}%")
                ->orWhere('id', $busca);
            })
            ->limit(10)
            ->get();
    }

    public function vincularCotacao(Request $request, int $id)
    {
        $validated = $request->validate([
            'cotacao_id' => 'required|integer|exists:cotacoes,id',
        ]);

        $q = \App\Models\Questionario::findOrFail($id);

        $nova = \App\Models\Cotacao::findOrFail($validated['cotacao_id']);

        // regra de negócio: não permitir vincular cotações já convertidas
        if ($nova->status === 'convertido') {
            return response()->json([
                'message' => 'Esta cotação já está convertida e não pode ser vinculada.'
            ], 422);
        }

        // vincula ao questionário
        $q->cotacao_id = $nova->id;
        $q->save();

        SyncCotacaoStatusService::syncFromQuestionario($q);

        return response()->json($q->load('cotacao'));
    }

}
