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
        Schema::create('vlf_jugadores_equipo', function (Blueprint $table) {
            $table->unsignedSmallInteger('id_jugador');
            $table->unsignedSmallInteger('id_equipo');
            
            // PK
            $table->primary(['id_jugador', 'id_equipo']);
            // FK
            $table->foreign('id_jugador')->references('id')->on('vlf_jugadores')->onDelete('cascade');
            $table->foreign('id_equipo')->references('id')->on('vlf_equipos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_jugadores_equipo');
    }
};
