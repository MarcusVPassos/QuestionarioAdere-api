<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rotacao extends Model
{
    protected $table = 'rotacoes';
    protected $fillable = ['ultimo_id', 'proximo_id'];
}
