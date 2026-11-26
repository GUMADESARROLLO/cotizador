<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Envío manual a Softland (botón en el detalle del pedido).
            $table->string('softland_estado', 10)->nullable()->after('credito_disponible'); // null | enviando | enviado | error
            $table->string('softland_pedido', 50)->nullable()->after('softland_estado')->index(); // umk.PEDIDO.PEDIDO
            $table->timestamp('softland_enviado_at')->nullable()->after('softland_pedido');
            $table->text('softland_error')->nullable()->after('softland_enviado_at');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['softland_estado', 'softland_pedido', 'softland_enviado_at', 'softland_error']);
        });
    }
};
