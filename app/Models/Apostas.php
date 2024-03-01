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
        'tipo_jogo_id',
        'jogo',
        'jogo_id',
        'bilhetes',
        'valor_aposta',
        'criacao_aposta'
    ];
}
