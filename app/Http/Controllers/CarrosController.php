<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

function podeGerenciarMarketing(Request $request): bool {
    $role = $request->user()?->role;
    return in_array($role, ['diretoria','marketing'], true);
}

class CarrosController extends Controller
{
    public function index(Request $request) {
        $tipo   = $request->query('tipo');     // ex.: Sedan
        $modelo = $request->query('modelo');   // ex.: Kwid

        $q = Carro::query()
            ->with('modelo:id,nome,tipo')
            ->where('ativo', true)
            ->when($modelo, fn($w) => $w->whereHas('modelo', fn($m) => $m->where('nome', $modelo)))
            ->when($tipo, function($w) use ($tipo) {
                $w->where(function($x) use ($tipo) {
                    $x->whereHas('modelo', fn($m) => $m->where('tipo', $tipo))
                      ->orWhereJsonContains('tipos', $tipo);
                });
            })
            ->orderBy('titulo');

        return $q->get();
    }

    public function store(Request $request) {
        if (!podeGerenciarMarketing($request)) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        if ($request->has('ativo')) {
            $request->merge(['ativo' => $request->boolean('ativo')]);
        }

        $data = $request->validate([
            'modelo_id'   => 'required|exists:modelos,id',
            'titulo'      => 'required|string',
            'imagem'      => 'nullable|image|max:5120',
            'imagem_url'  => 'nullable|string',
            'tipos'       => 'nullable|array',
            'tipos.*'     => ['string', Rule::exists('tipos','nome')->where('ativo', 1)],
            'ativo'       => 'boolean',
        ]);

        if (!$request->hasFile('imagem') && empty($data['imagem_url'])) {
            return response()->json(['error' => 'Envie "imagem" (arquivo) ou "imagem_url" (string).'], 422);
        }

        // se veio arquivo, gera URL absoluta do disk public
        $publicUrl = $data['imagem_url'] ?? null;
        if ($request->hasFile('imagem')) {
            $path = $request->file('imagem')->store('carros', 'public'); // carros/arquivo.ext
            $publicUrl = asset('storage/' . $path);                      // http://127.0.0.1:8000/storage/carros/...
        }

        $carro = Carro::create([
            'modelo_id'  => $data['modelo_id'],
            'titulo'     => $data['titulo'],
            'imagem_url' => $publicUrl,
            'tipos'      => $data['tipos'] ?? null,
            'ativo'      => $data['ativo'] ?? true,
        ]);

        return response()->json($carro, 201);
    }

    public function update(Request $request, Carro $carro) {
        if (!podeGerenciarMarketing($request)) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        if ($request->has('ativo')) {
            $request->merge(['ativo' => $request->boolean('ativo')]);
        }

        $data = $request->validate([
            'modelo_id'   => 'required|exists:modelos,id',
            'titulo'      => 'required|string',
            'imagem'      => 'nullable|image|max:5120',
            'imagem_url'  => 'nullable|string',
            'tipos'       => 'nullable|array',
            'tipos.*'     => ['string', Rule::exists('tipos','nome')->where('ativo', 1)],
            'ativo'       => 'boolean',
        ]);

        $publicUrl = $carro->imagem_url;

        if ($request->hasFile('imagem')) {
            $this->deleteIfPublic($carro->imagem_url);
            $path = $request->file('imagem')->store('carros', 'public');
            $publicUrl = asset('storage/' . $path);
        } elseif (!empty($data['imagem_url'])) {
            $publicUrl = $data['imagem_url']; // mantém URL externa, se enviada
        }

        $carro->update([
            'modelo_id'  => $data['modelo_id'],
            'titulo'     => $data['titulo'],
            'imagem_url' => $publicUrl,
            'tipos'      => $data['tipos'] ?? null,
            'ativo'      => $data['ativo'] ?? $carro->ativo,
        ]);

        return response()->json($carro);
    }

    public function destroy(Request $request, Carro $carro) {
        if (!podeGerenciarMarketing($request)) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        // apaga imagem se for do storage público local
        $this->deleteIfPublic($carro->imagem_url);

        $carro->delete();
        return response()->json(['message'=>'Excluído']);
    }

    /** Se a URL for do storage público local (/storage/...), remove o arquivo físico */
    private function deleteIfPublic(?string $publicUrl): void
    {
        if (!$publicUrl) return;

        // Aceitamos tanto "/storage/..." quanto "http(s)://host/storage/..."
        $path = null;
        if (str_starts_with($publicUrl, '/storage/')) {
            $path = substr($publicUrl, strlen('/storage/')); // carros/arquivo.ext
        } else {
            $pos = strpos($publicUrl, '/storage/');
            if ($pos !== false) {
                $path = substr($publicUrl, $pos + strlen('/storage/'));
            }
        }

        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
