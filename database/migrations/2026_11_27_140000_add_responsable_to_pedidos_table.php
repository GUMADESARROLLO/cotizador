<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Línea verde bajo "Ref:" de la cotización: cambia en cada cotización, va con el pedido.
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('responsable', 150)->nullable()->after('licitacion');
        });

        // Instalaciones que alcanzaron a crear la ficha con "responsable" por cliente.
        if (Schema::hasColumn('cliente_fichas', 'responsable')) {
            Schema::table('cliente_fichas', fn (Blueprint $table) => $table->dropColumn('responsable'));
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }
};
