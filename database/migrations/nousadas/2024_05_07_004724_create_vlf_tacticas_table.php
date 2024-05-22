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
        Schema::create('vlf_tacticas', function (Blueprint $table) {
            $table->TinyIncrements('id');
            $table->string('nombre', 30);
            $table->string('abreviatura', 1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlf_tacticas');
    }
};
