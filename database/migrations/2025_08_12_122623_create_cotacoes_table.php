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
        Schema::create('cotacoes', function (Blueprint $table) {
            $table->id();

            // Lead
            $table->enum('pessoa', ['pf', 'pj']);
            $table->string('nome_razao');
            $table->string('cpf', 14)->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->string('telefone', 20);
            $table->string('email');

            // Origem e mensagem
            $table->string('origem')->nullable(); // normalizamos META ADS quando vier IG/FB
            $table->text('mensagem')->nullable();

            // Pedido
            $table->string('tipo_veiculo')->nullable();
            $table->string('modelo_veiculo')->nullable();
            $table->date('data_precisa')->nullable();
            $table->string('tempo_locacao')->nullable();

            // Tipo venda
            $table->enum('tipo_venda', ['diario', 'mensal', 'assinatura'])->nullable();

            // Gerenciais
            $table->enum('status', ['novo','em_atendimento','negociacao','recusado','convertido'])->default('novo');
            $table->string('status_detalhe')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recepcionista_id')->nullable()->constrained('users')->nullOnDelete();

            // Extras
            $table->json('canal_raw')->nullable();      // payload original, se quiser guardar
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->index(['status','created_at']);
            $table->index(['vendedor_id','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotacoes');
    }
};
