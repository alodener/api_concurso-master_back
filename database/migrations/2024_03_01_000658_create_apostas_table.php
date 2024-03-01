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
            $table->integer('tipo_jogo_id');
            $table->string('jogo');
            $table->integer('jogo_id');
            $table->float('valor_aposta');
            $table->text('bilhete');
            $table->integer('banca_id');
            $table->integer('concurso');
            $table->string('participante');
            $table->integer('participante_id');
            $table->dateTime('criacao_aposta');
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
