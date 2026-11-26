<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            // Lote elegido (Softland PEDIDO_LINEA.LOTE) y su vencimiento al momento del pedido.
            $table->string('lote', 15)->nullable()->after('unidad');
            $table->date('lote_vence')->nullable()->after('lote');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn(['lote', 'lote_vence']);
        });
    }
};
