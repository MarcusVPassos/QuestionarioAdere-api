<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Carro extends Model
{
    protected $table = 'carros';
    protected $fillable = ['modelo_id','titulo','descricao','imagem_url','tipos','ativo'];
    protected $casts   = ['tipos' => 'array', 'ativo' => 'boolean'];

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class);
    }

    // Normaliza a URL para sempre ser absoluta
    public function getImagemUrlAttribute(?string $value): ?string
    {
        if (!$value) return null;

        // Já é absoluta (http/https) → retorna
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Veio como "/storage/..." → prefixa APP_URL
        if (str_starts_with($value, '/storage/')) {
            return rtrim(config('app.url'), '/') . $value;
        }

        // Veio como caminho relativo do disk public ("carros/arquivo.ext")
        return asset('storage/' . $value);
    }
}
