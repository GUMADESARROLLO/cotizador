<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Datos del cliente que el pedido de Softland copia (umk.CLIENTE).
            $table->string('nivel_precio', 12)->nullable()->after('cliente_ruc');
            $table->char('moneda', 1)->default('L')->after('nivel_precio'); // L = C$, D = US$ (moneda del nivel)
            $table->string('condicion_pago', 4)->nullable()->after('moneda');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['nivel_precio', 'moneda', 'condicion_pago']);
        });
    }
};
