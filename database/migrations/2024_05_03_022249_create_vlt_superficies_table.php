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
        Schema::create('vlt_superficies', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 20);
            $table->tinyInteger('modificador');
            // PK
            //$table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlt_superficies');
    }
};
