<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    public function download(Documento $documento)
    {
        if (!Storage::exists($documento->caminho)) {
            return response()->json(['message' => 'Arquivo não encontrado'], 404);
        }

        return Storage::download($documento->caminho, $documento->nome_original);
    }
}
