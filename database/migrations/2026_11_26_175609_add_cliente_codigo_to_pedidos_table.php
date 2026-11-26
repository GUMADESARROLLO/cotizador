<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Código del cliente en GMV_Clientes (el nombre ya se guarda en "cliente").
            $table->string('cliente_codigo', 20)->nullable()->after('folio')->index();
            $table->string('cliente_ruc', 150)->nullable()->after('cliente');
            // Crédito disponible al momento del pedido (referencia).
            $table->decimal('credito_disponible', 14, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['cliente_codigo', 'cliente_ruc', 'credito_disponible']);
        });
    }
};
