<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique()->nullable();
            $table->string('cliente', 150);
            $table->string('contacto', 150)->nullable();
            $table->text('notas')->nullable();
            $table->unsignedInteger('lineas')->default(0);
            $table->decimal('piezas', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
