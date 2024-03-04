<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apostas extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome_usuario',
        'usuario_id',
        'tipo_jogo',
        'jogo',
        'jogo_id',
        'valor_aposta',
        'valor_premio',
        'bilhete',
        'concurso'
    ];
}
