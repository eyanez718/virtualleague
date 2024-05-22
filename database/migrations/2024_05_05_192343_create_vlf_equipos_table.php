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
        Schema::create('vlf_equipos', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('nombre', 40);
            $table->unsignedSmallInteger('id_nacionalidad');
            $table->unsignedSmallInteger('id_usuario')->nullable();
            // FK
            $table->foreign('id_nacionalidad')->references('id')->on('vl_nacionalidades');
            $table->foreign('id_usuario')->references('id')->on('vl_usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_equipos');
    }
};
