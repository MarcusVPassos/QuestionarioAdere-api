<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Questionario extends Model
{
    use HasFactory;

    protected $fillable = ['tipo', 'dados', 'status', 'motivo_negativa', 'user_id'];

    protected $casts = [
        'dados' => 'array',
    ];

    public function documentos()
    {
        return $this->hasMany(Documento::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
