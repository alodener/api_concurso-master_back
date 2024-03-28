<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWinnersListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('winners_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('banca_id');
            $table->json('fake_winners');
            $table->decimal('fake_premio', 10, 2);
            $table->json('json');
            $table->timestamps();

            // Definindo a chave estrangeira
            $table->foreign('banca_id')->references('id')->on('partners')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('winners_lists');
    }
}
