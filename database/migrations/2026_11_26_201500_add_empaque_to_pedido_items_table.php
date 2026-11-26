<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captura en unidades (viales, tabletas…) además de cajas, y precio manual.
 *
 * La cantidad sigue en CAJAS (lo que va a Softland), ahora con fracción:
 * 2,810 viales con factor 50 = 56.2 cajas; con factor 28, 1 tableta = 0.03571429.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->decimal('cantidad', 18, 8)->change();
            $table->decimal('precio', 18, 4)->change(); // por caja
            $table->string('modo', 10)->default('caja')->after('cantidad'); // unidad | caja
            $table->decimal('unidades', 18, 4)->nullable()->after('modo');
            $table->decimal('factor', 12, 4)->default(1)->after('unidades');
            $table->string('unidad_empaque', 20)->nullable()->after('factor');
            $table->decimal('precio_nivel', 18, 4)->nullable()->after('precio');
            $table->boolean('precio_manual')->default(false)->after('precio_nivel');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('piezas', 18, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn(['modo', 'unidades', 'factor', 'unidad_empaque', 'precio_nivel', 'precio_manual']);
            $table->decimal('cantidad', 14, 2)->change();
            $table->decimal('precio', 14, 2)->change();
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('piezas', 14, 2)->default(0)->change();
        });
    }
};
