<?php

namespace Tests\Feature;

use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Documentos adjuntos: solo pedidos ingresados en Softland; archivos en el bucket, carpeta Cotizaciones/. */
class AdjuntosPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        $this->app->instance(Clientes::class, new DemoClientes);
    }

    private function pedido(bool $enviado = true): Pedido
    {
        $p = Pedido::create(['cliente' => 'FARMACIA LOPEZ', 'cliente_codigo' => '00005', 'moneda' => 'L', 'lineas' => 1, 'piezas' => 1, 'total' => 10]);
        $p->items()->create(['codigo' => 'A1', 'descripcion' => 'Anastrozol 1 mg', 'unidad' => 'CJA', 'precio' => 10, 'cantidad' => 1, 'existencia' => 0, 'subtotal' => 10]);
        if ($enviado) {
            $p->forceFill(['softland_estado' => Pedido::SOFTLAND_ENVIADO, 'softland_pedido' => '00007839'])->save();
        }

        return $p;
    }

    public function test_el_boton_adjuntar_solo_aparece_si_el_pedido_esta_en_softland(): void
    {
        $this->get(route('pedidos.show', $this->pedido(false)))->assertOk()->assertDontSee('data-adjuntos-abrir', false);
        $this->get(route('pedidos.show', $this->pedido()))->assertOk()
            ->assertSee('data-adjuntos-abrir', false)->assertSee('Adjuntar')
            ->assertSee('Arrastra los archivos aquí');
    }

    public function test_sube_varios_archivos_a_la_carpeta_cotizaciones_y_los_lista(): void
    {
        $p = $this->pedido();

        $res = $this->postJson(route('pedidos.adjuntos.subir', $p), ['archivos' => [
            UploadedFile::fake()->create('Orden de Compra 121164.pdf', 120, 'application/pdf'),
            UploadedFile::fake()->image('foto factura.jpg'),
        ]])->assertOk()->assertJsonPath('message', '2 archivos adjuntados.');

        $this->assertCount(2, $res->json('adjuntos'));
        $this->assertSame(2, $p->adjuntos()->count());
        foreach ($p->adjuntos as $a) {
            $this->assertStringStartsWith('Cotizaciones/00007839/', $a->ruta);
            Storage::disk('s3')->assertExists($a->ruta);
        }
        $this->assertSame(['foto factura.jpg', 'Orden de Compra 121164.pdf'], $p->adjuntos->pluck('nombre')->all());
        $this->assertMatchesRegularExpression('#^Cotizaciones/00007839/orden-de-compra-121164_[a-z0-9]{6}\.pdf$#', $p->adjuntos->last()->ruta);

        $this->getJson(route('pedidos.adjuntos', $p))->assertOk()->assertJsonCount(2, 'adjuntos')
            ->assertJsonPath('adjuntos.0.extension', 'JPG');
        // El botón muestra cuántos hay.
        $this->get(route('pedidos.show', $p))->assertSee('data-adjuntos-n>2<', false);
    }

    public function test_descarga_con_el_nombre_original_y_elimina_del_bucket(): void
    {
        $p = $this->pedido();
        $this->postJson(route('pedidos.adjuntos.subir', $p), ['archivos' => [UploadedFile::fake()->createWithContent('Cotización firmada.pdf', '%PDF-1.4 prueba')]])->assertOk();
        $a = $p->adjuntos()->sole();

        $descarga = $this->get(route('pedidos.adjuntos.descargar', [$p, $a]))->assertOk();
        $this->assertStringContainsString('Cotizaci', $descarga->headers->get('content-disposition'));
        $this->assertSame('%PDF-1.4 prueba', $descarga->streamedContent());

        $this->deleteJson(route('pedidos.adjuntos.eliminar', [$p, $a]))->assertOk()->assertJsonCount(0, 'adjuntos');
        Storage::disk('s3')->assertMissing($a->ruta);
        $this->assertSame(0, $p->adjuntos()->count());
    }

    public function test_valida_tipo_y_tamano_y_no_permite_pedidos_sin_enviar(): void
    {
        $p = $this->pedido();
        $this->postJson(route('pedidos.adjuntos.subir', $p), ['archivos' => [UploadedFile::fake()->create('virus.exe', 10)]])
            ->assertStatus(422);
        config(['cotizacion.adjuntos.max_kb' => 100]);
        $this->postJson(route('pedidos.adjuntos.subir', $p), ['archivos' => [UploadedFile::fake()->create('grande.pdf', 500, 'application/pdf')]])
            ->assertStatus(422);
        $this->postJson(route('pedidos.adjuntos.subir', $p), [])->assertStatus(422)->assertJsonValidationErrors('archivos');
        $this->assertSame(0, $p->adjuntos()->count());

        $sinEnviar = $this->pedido(false);
        $this->postJson(route('pedidos.adjuntos.subir', $sinEnviar), ['archivos' => [UploadedFile::fake()->create('a.pdf', 10)]])->assertStatus(409);
        $this->getJson(route('pedidos.adjuntos', $sinEnviar))->assertStatus(409);
    }

    public function test_no_se_descarga_ni_borra_un_adjunto_de_otro_pedido(): void
    {
        $p1 = $this->pedido();
        $p2 = $this->pedido();
        $this->postJson(route('pedidos.adjuntos.subir', $p1), ['archivos' => [UploadedFile::fake()->create('a.pdf', 10)]])->assertOk();
        $a = $p1->adjuntos()->sole();

        $this->get(route('pedidos.adjuntos.descargar', [$p2, $a]))->assertNotFound();
        $this->deleteJson(route('pedidos.adjuntos.eliminar', [$p2, $a]))->assertNotFound();
        $this->assertSame(1, $p1->adjuntos()->count());
    }
}
