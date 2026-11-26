<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Fecha del pedido elegida en el calendario (puede ser posterior a la de creación,
            // p. ej. se arma hoy con fecha del lunes). Va a FECHA_PEDIDO en Softland.
            $table->date('fecha_pedido')->nullable()->after('folio');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('fecha_pedido');
        });
    }
};
