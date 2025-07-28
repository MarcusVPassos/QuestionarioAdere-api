<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotacao extends Model
{
    protected $fillable = ['data'];
    public $timestamps = true;

    protected $table = 'cotacoes';
}
