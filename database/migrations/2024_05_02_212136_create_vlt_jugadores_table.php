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
        Schema::create('vlt_jugadores', function (Blueprint $table) {
            // Campos
            $table->smallIncrements('id');
            $table->string('nombre', 40);
            $table->unsignedSmallInteger('id_nacionalidad');
            $table->unsignedSmallInteger('id_usuario')->nullable();
            $table->unsignedTinyInteger('id_superficie');
            $table->tinyInteger('hab_derecha');
            $table->tinyInteger('hab_reves');
            $table->tinyInteger('hab_volea');
            $table->tinyInteger('hab_dejada');
            $table->tinyInteger('hab_velocidad');
            $table->tinyInteger('hab_resistencia');
            $table->tinyInteger('hab_servicio');
            $table->tinyInteger('hab_potencia');
            $table->tinyInteger('hab_slice');
            $table->tinyInteger('hab_consistencia');
            $table->tinyInteger('hab_forma');
            //PK
            //$table->primary('id');
            //FK
            $table->foreign('id_nacionalidad')->references('id')->on('vl_nacionalidades');
            $table->foreign('id_usuario')->references('id')->on('vl_usuarios');
            $table->foreign('id_superficie')->references('id')->on('vlt_superficies');
            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlt_jugadores');
    }
};
