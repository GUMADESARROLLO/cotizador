<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos adjuntos a un pedido ya ingresado en Softland. El archivo vive en el
        // bucket (carpeta Cotizaciones/); aquí solo el registro para listarlo.
        Schema::create('pedido_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 255);        // nombre original del archivo
            $table->string('ruta', 500);          // clave en el bucket
            $table->string('tipo', 120)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_adjuntos');
    }
};
