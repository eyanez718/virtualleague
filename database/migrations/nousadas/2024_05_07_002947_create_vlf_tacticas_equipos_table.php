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
        Schema::create('vlf_tactica_equipo', function (Blueprint $table) {
            $table->unsignedSmallInteger('id_equipo');
            $table->string('ids_jugadores', 150);
            $table->unsignedTinyInteger('id_tactica');
            $table->unsignedSmallInteger('id_pateador_penal');
            // PK
            $table->primary('id_equipo');
            // FK
            $table->foreign('id_equipo')->references('id')->on('vlf_equipos')->onDelete('cascade');
            $table->foreign('id_tactica')->references('id')->on('vlf_tacticas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_tactica_equipo');
    }
};
