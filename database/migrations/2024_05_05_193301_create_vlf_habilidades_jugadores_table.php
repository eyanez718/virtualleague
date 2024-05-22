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
        Schema::create('vlf_habilidades_jugadores', function (Blueprint $table) {
            $table->unsignedSmallInteger('id_jugador');
            $table->string('lado_preferido', 3);
            $table->tinyInteger('habilidad_arquero')->default(1);
            $table->tinyInteger('habilidad_quite')->default(1);
            $table->tinyInteger('habilidad_pase')->default(1);
            $table->tinyInteger('habilidad_tiro')->default(1);
            $table->tinyInteger('agresividad')->default(1);
            $table->tinyInteger('resistencia')->default(1);
            $table->smallInteger('progreso_arquero')->default(0);
            $table->smallInteger('progreso_quite')->default(0);
            $table->smallInteger('progreso_pase')->default(0);
            $table->smallInteger('progreso_tiro')->default(0);
            $table->tinyInteger('fisico')->default(1);
            // PK
            $table->primary('id_jugador');
            // FK
            $table->foreign('id_jugador')->references('id')->on('vlf_jugadores')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_habilidades_jugadores');
    }
};
