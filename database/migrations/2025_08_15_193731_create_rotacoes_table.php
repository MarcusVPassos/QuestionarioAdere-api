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
        Schema::create('rotacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ultimo_id')->nullable();  // último vendedor usado
            $table->unsignedBigInteger('proximo_id')->nullable(); // já calculado e reservado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotacoes');
    }
};
