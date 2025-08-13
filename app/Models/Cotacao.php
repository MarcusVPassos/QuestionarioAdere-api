<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cotacao extends Model
{
    protected $table = 'cotacoes';

    protected $fillable = [
        'pessoa','nome_razao','cpf','cnpj','telefone','email',
        'origem','mensagem',
        'tipo_veiculo','modelo_veiculo','data_precisa','tempo_locacao',
        'status','status_detalhe','motivo_recusa','vendedor_id','recepcionista_id','canal_raw','observacoes',
    ];

    protected $casts = [
        'data_precisa' => 'date:Y-m-d',
        'canal_raw' => 'array',
    ];

    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function recepcionista(): BelongsTo { return $this->belongsTo(User::class, 'recepcionista_id'); }
}
