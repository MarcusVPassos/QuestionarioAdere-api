<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Questionario extends Model
{
    use HasFactory;

    protected $fillable = ['tipo', 'dados', 'status', 'motivo_negativa', 'user_id', 'tipo_venda', 'valor_venda',
    'valor_venda_total', 'valor_mensalidade', 'percentual_comissao', 'valor_comissao_calculado', 'comentario_correcao','cotacao_id'];

    protected $casts = [
        'dados' => 'array',
        'valor_venda' => 'decimal:2',
        'valor_venda_total' => 'decimal:2',
        'valor_mensalidade' => 'decimal:2',
        'percentual_comissao' => 'decimal:4',
        'valor_comissao_calculado' => 'decimal:2',
    ];

    public function documentos()
    {
        return $this->hasMany(Documento::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cotacao()
    {
        return $this->belongsTo(Cotacao::class);
    }
}
