<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modelo extends Model
{
    protected $table = 'modelos';
    protected $fillable = ['nome','tipo','ativo'];
    protected $casts = ['ativo' => 'boolean'];

    public function carros(): HasMany
    {
        return $this->hasMany(Carro::class);
    }
}
