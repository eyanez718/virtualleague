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
        Schema::create('vlf_modificadores_tactica_posicion', function (Blueprint $table) {
            $table->unsignedTinyInteger('id_tactica');
            $table->unsignedTinyInteger('id_posicion_jugador');
            $table->string('tipo_modificador', 15);
            $table->unsignedTinyInteger('id_tactica_bonus')->nullable();
            $table->float('mod_quite')->default(0);
            $table->float('mod_pase')->default(0);
            $table->float('mod_tiro')->default(0);
            // PK
            $table->primary(['id_tactica', 'id_posicion_jugador', 'tipo_modificador', 'id_tactica_bonus']);
            // FK
            $table->foreign('id_tactica')->references('id')->on('vlf_tacticas')->onDelete('cascade');
            $table->foreign('id_posicion_jugador')->references('id')->on('vlf_posiciones_jugador')->onDelete('cascade');
            $table->foreign('id_tactica_bonus')->references('id')->on('vlf_tacticas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_modificadores_tactica_posicion');
    }
};
