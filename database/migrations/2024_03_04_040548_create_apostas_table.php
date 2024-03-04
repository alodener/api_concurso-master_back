<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apostas', function (Blueprint $table) {
            $table->id();
            $table->string('nome_usuario');
            $table->integer('usuario_id');
            $table->string('tipo_jogo');
            $table->string('jogo');
            $table->integer('jogo_id');
            $table->float('valor_aposta');
            $table->float('valor_premio');
            $table->string('bilhete');
            $table->string('concurso');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas');
    }
};
