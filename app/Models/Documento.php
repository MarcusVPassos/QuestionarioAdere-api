<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Documento extends Model
{
    use HasFactory;

    protected $fillable = ['questionario_id', 'nome_original', 'caminho', 'mime_type'];

    protected $appends = ['url'];

    public function questionario()
    {
        return $this->belongsTo(Questionario::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->caminho);
    }
}
