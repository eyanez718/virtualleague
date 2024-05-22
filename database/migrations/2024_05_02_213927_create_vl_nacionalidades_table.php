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
        Schema::create('vl_nacionalidades', function (Blueprint $table) {
            // Campos
            $table->smallIncrements('id');
            $table->string('nombre', 30)->unique();
            $table->string('abreviatura', 3)->unique();
            // PK
            //$table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vl_nacionalidades');
    }
};
