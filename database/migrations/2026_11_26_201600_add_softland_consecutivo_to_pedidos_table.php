<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutivo de Softland elegido para el pedido (DESP-A, DESP-B…).
 * null = el de config/softland.php (pedidos anteriores a poder elegirlo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('softland_consecutivo', 20)->nullable()->after('softland_pedido');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('softland_consecutivo');
        });
    }
};
