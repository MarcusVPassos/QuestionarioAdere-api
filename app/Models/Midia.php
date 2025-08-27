<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Midia extends Model
{
    protected $table = 'midias';
    protected $fillable = ['nome', 'slug', 'ativo'];
    protected $casts = ['ativo' => 'boolean'];
}
