<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Questionario;
use App\Models\Documento;
use Illuminate\Support\Facades\Storage;


class QuestionarioController extends Controller
{

    public function index()
    {
        return Questionario::with('documentos')->get();
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

    return response()->json([
        'message' => 'Questionário atualizado com sucesso.',
        'status' => 'pendente',
    ]);
}


}
