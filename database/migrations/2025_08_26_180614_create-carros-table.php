<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_id')->constrained('modelos')->cascadeOnDelete();
            $table->string('titulo');           // exibição no marketing
            $table->string('imagem_url');       // URL pública (Drive, CDN ou /storage)
            $table->json('tipos')->nullable();  // opcional: sobrescreve tipos do modelo
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['modelo_id','ativo']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('carros');
    }
};
