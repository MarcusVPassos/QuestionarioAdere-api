<?php

namespace App\Http\Controllers;

use App\Events\NovoQuestionarioCriado;
use Illuminate\Http\Request;
use App\Models\Questionario;
use App\Models\Documento;
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


    public function store(Request $request)
    {
        $request->validate([
            'dados' => 'required|json',
            'documentos.*' => 'file|max:5120', // máx 5MB por arquivo
        ]);

        $dados = json_decode($request->dados, true);

        if (!in_array($dados['tipo'], ['pf', 'pj'])) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        $questionario = Questionario::create([
            'tipo' => $dados['tipo'],
            'dados' => $dados,
            'status' => 'pendente',
            'user_id' => $request->user()->id,
        ]);

        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos') as $file) {
                $path = $file->store('documentos', 'public');

                Documento::create([
                    'questionario_id' => $questionario->id,
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho' => $path,
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }
        }

        // 3) Recarrega defaults e a relação
        $questionario->refresh();               // carrega o default do DB (status = 'pendente')
        $questionario->load('documentos');      // carrega a coleção de documentos

        event(new NovoQuestionarioCriado($questionario));
        Cache::flush();

        return response()->json([
            'message' => 'Questionário enviado com sucesso',
            'protocolo' => $questionario->id,
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

    public function update(Request $request, $id)
    {
        $dadosRaw = $request->input('dados');

        if (!$dadosRaw) {
            return response()->json(['message' => 'O campo "dados" é obrigatório.'], 422);
        }

        $dados = json_decode($dadosRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            return response()->json(['message' => 'O campo "dados" deve ser um JSON válido.'], 422);
        }

        $request->merge(['dados' => $dados]);

        $request->validate([
            'dados' => 'required|array',
            'documentos.*' => 'file|max:5120',
        ]);

        if (!in_array($dados['tipo'] ?? '', ['pf', 'pj'])) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        $questionario = Questionario::findOrFail($id);
        $questionario->dados = $dados;
        $questionario->status = 'pendente';
        $questionario->comentario_correcao = null;
        $questionario->motivo_negativa = null;
        $questionario->save();

        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos') as $file) {
                $path = $file->store('documentos', 'public');

                Documento::create([
                    'questionario_id' => $questionario->id,
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho' => $path,
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }
        }

        $questionario->refresh()->load('documentos'); // carrega dados atualizados + documentos
        event(new NovoQuestionarioCriado($questionario));
        Cache::flush();

        return response()->json([
            'message' => 'Questionário atualizado com sucesso.',
            'status' => 'pendente',
        ]);
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
}
