<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos del cliente para la cotización en Excel, recordados por cliente: se escriben
        // una vez (Softland no los tiene o los tiene desactualizados) y se reutilizan.
        Schema::create('cliente_fichas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_codigo', 20)->unique();
            $table->string('nombre', 150)->nullable();      // "Cliente:" tal como debe salir
            $table->string('atencion', 150)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->string('telefono', 60)->nullable();
            $table->string('fax', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_fichas');
    }
};
