<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotacaoGoogle extends Model
{
    protected $table = 'cotacoes_google';

    protected $fillable = [
        'data',
        'total',
    ];

    protected $casts = [
        'data' => 'date',
    ];
}
