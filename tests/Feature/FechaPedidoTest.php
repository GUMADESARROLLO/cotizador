<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\DemoLotes;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Models\Pedido;
use App\Softland\PedidoSoftland;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fecha del pedido elegida en el calendario (p. ej. se arma el jueves con fecha del lunes).
 */
class FechaPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00'); // jueves

        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Artículo uno', 'unidad' => 'CJA', 'precio' => 100, 'existencia' => 50],
        ]));
        $this->app->instance(Lotes::class, new DemoLotes(lotes: ['A1' => [new Lote('LA-1', 50, '2027-01-31')]]));

        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 2]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_por_defecto_es_hoy_y_se_muestra_el_calendario(): void
    {
        $this->get('/pedido')->assertOk()
            ->assertSee('Fecha del pedido')
            ->assertSee('Jue 26 nov 2026')
            ->assertSee('data-fecha="2026-11-26"', false)
            ->assertSee('data-max="2027-01-25"', false) // hoy + 60 días
            ->assertSee('data-fecha-sheet', false);
    }

    public function test_elegir_el_lunes(): void
    {
        $this->postJson('/pedido/fecha', ['fecha' => '2026-11-30'])
            ->assertOk()->assertJson(['fecha' => '2026-11-30', 'etiqueta' => 'lunes 30 de noviembre de 2026', 'corta' => 'Lun 30 nov 2026']);

        $this->get('/pedido')->assertSee('Lun 30 nov 2026');
    }

    public function test_acepta_fechas_anteriores_dentro_del_rango(): void
    {
        // Ingresar hoy un pedido de ayer (o de semanas atrás) es lo normal.
        $this->postJson('/pedido/fecha', ['fecha' => '2026-11-25'])->assertOk()->assertJson(['corta' => 'Mié 25 nov 2026']);
        $this->postJson('/pedido/fecha', ['fecha' => '2026-09-27'])->assertOk(); // hoy − 60 días

        $this->postJson('/pedido/fecha', ['fecha' => '2026-09-26'])->assertStatus(422)
            ->assertJsonPath('errors.fecha.0', 'La fecha del pedido puede ser como mínimo el domingo 27 de septiembre de 2026.');
        $this->postJson('/pedido/fecha', ['fecha' => '2027-03-01'])->assertStatus(422);
        $this->postJson('/pedido/fecha', ['fecha' => '30/11/2026'])->assertStatus(422);

        $this->get('/pedido')->assertSee('data-min="2026-09-27"', false)->assertSee('data-hoy="2026-11-26"', false);
    }

    public function test_se_guarda_con_el_pedido_y_se_carga_al_modificar(): void
    {
        $this->postJson('/pedido/fecha', ['fecha' => '2026-11-30']);
        $this->post('/pedidos', []);

        $p = Pedido::sole();
        $this->assertSame('2026-11-30', $p->fecha_pedido->format('Y-m-d'));
        // (la mayúscula inicial la pone el CSS)
        $this->get(route('pedidos.show', $p))->assertSee('Lun 30 nov 2026')->assertSee('Creado');
        $this->get('/pedidos')->assertSee('30/11/2026');

        // El siguiente pedido vuelve a empezar con hoy.
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1]);
        $this->get('/pedido')->assertSee('data-fecha="2026-11-26"', false);
        $this->delete('/pedido');

        // Al modificar, trae su fecha.
        $this->post(route('pedidos.editar', $p));
        $this->get('/pedido')->assertSee('data-fecha="2026-11-30"', false);
    }

    public function test_pedido_con_fecha_anterior_se_guarda_y_la_conserva_al_modificar(): void
    {
        $this->postJson('/pedido/fecha', ['fecha' => '2026-11-24']);
        $this->post('/pedidos', [])->assertRedirect();

        $p = Pedido::sole();
        $this->assertSame('2026-11-24', $p->fecha_pedido->format('Y-m-d'));

        Carbon::setTestNow('2026-11-28 09:00:00'); // se modifica días después
        $this->post(route('pedidos.editar', $p))->assertSessionHas('ajustados', []);
        $this->get('/pedido')->assertSee('data-fecha="2026-11-24"', false);
    }

    public function test_softland_usa_la_fecha_elegida_y_la_de_creacion_donde_corresponde(): void
    {
        Schema::create('CLIENTE', function ($t) {
            foreach (['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'DIR_EMB_DEFAULT', 'DIVISION_GEOGRAFICA1', 'DIVISION_GEOGRAFICA2', 'CONDICION_PAGO', 'NIVEL_PRECIO', 'MONEDA_NIVEL'] as $c) {
                $t->string($c)->nullable();
            }
        });
        DB::table('CLIENTE')->insert(['CLIENTE' => '00003', 'NOMBRE' => 'X', 'MONEDA_NIVEL' => 'L']);
        Schema::create('ARTICULO', fn ($t) => [$t->string('ARTICULO'), $t->string('IMPUESTO'), $t->string('ARTICULO_CUENTA')]);
        Schema::create('IMPUESTO', fn ($t) => [$t->string('IMPUESTO'), $t->decimal('IMPUESTO1', 10, 4)]);
        Schema::create('ARTICULO_CUENTA', fn ($t) => [$t->string('ARTICULO_CUENTA'), $t->string('CTA_VENTAS_LOC'), $t->string('CTR_VENTAS_LOC')]);
        config(['softland.esquema' => 'main']);

        $prep = (new PedidoSoftland(DB::connection(), config('softland')))->preparar(
            '00007836', '00003', [['codigo' => 'A1', 'precio' => 1, 'cantidad' => 1, 'lote' => 'LA-1']],
            now(), null, Carbon::parse('2026-11-30'),
        );
        $e = array_map(fn ($x) => $x['valor'], $prep['encabezado']);
        $l = array_map(fn ($x) => $x['valor'], $prep['lineas'][0]);

        // Igual que el pedido real 00007807 (creado el 10/11 con fecha del 11/11).
        foreach (['FECHA_PEDIDO', 'FECHA_PROMETIDA', 'FECHA_PROX_EMBARQU'] as $c) {
            $this->assertSame('2026-11-30 00:00:00.000', $e[$c], $c);
        }
        // Como el pedido 00007836 creado en Softland: al crearse queda en 1980-01-01.
        $this->assertSame('1980-01-01 00:00:00.000', $e['FECHA_ULT_EMBARQUE']);
        $this->assertSame('2026-11-26 00:00:00.000', $e['FECHA_ORDEN']);
        $this->assertSame('2026-11-26 00:00:00.000', $e['FECHA_PROYECTADA']);
        $this->assertSame('2026-11-26 10:00:00.000', $e['FECHA_HORA']);
        $this->assertSame(['2026-11-30 00:00:00.000', '2026-11-30 00:00:00.000'], [$l['FECHA_ENTREGA'], $l['FECHA_PROMETIDA']]);
    }
}
