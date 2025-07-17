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
}
