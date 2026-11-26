<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            // Copia de los datos de SQL Server al momento del pedido.
            $table->string('codigo', 50)->index();
            $table->string('descripcion', 255);
            $table->string('unidad', 20)->nullable();
            $table->decimal('precio', 14, 2);
            $table->decimal('cantidad', 14, 2);
            $table->decimal('existencia', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_items');
    }
};
