<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('cotacoes', function (Blueprint $table) {
            if (!Schema::hasColumn('cotacoes','origem')) return;
            $table->index(['origem', 'created_at'], 'cotacoes_origem_created_idx');
        });
    }
    public function down(): void {
        Schema::table('cotacoes', function (Blueprint $table) {
            $table->dropIndex('cotacoes_origem_created_idx');
        });
    }
};
