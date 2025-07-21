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
        Schema::create('questionarios', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['pf', 'pj']);
            $table->json('dados');
            $table->enum('status', ['pendente', 'aprovado', 'negado', 'correcao'])->default('pendente');
            $table->text('motivo_negativa')->nullable();
            $table->text('comentario_correcao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionarios');
    }
};
