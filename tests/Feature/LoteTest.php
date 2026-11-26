<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\DemoLotes;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Catalogo\SqlServerLotes;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lote de despacho: sugerido el que vence primero, elegible a mano; la
 * cantidad se limita a la existencia del lote elegido.
 */
class LoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => '11715061', 'descripcion' => 'Con dos lotes', 'unidad' => 'CJA', 'precio' => 10, 'existencia' => 6700],
        ]));
        $this->lotes([
            '11715061' => [
                new Lote('F411250901', 3000, '2027-09-01'), // vence primero → sugerido
                new Lote('F411251101', 3700, '2027-11-24'),
            ],
        ]);

        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
    }

    private function lotes(array $lotes): void
    {
        $this->app->instance(Lotes::class, new DemoLotes(lotes: $lotes));
    }

    public function test_sugiere_el_que_vence_primero_y_avisa_si_excede(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 5000])
            ->assertOk()
            ->assertJson(['cantidad' => 5000, 'maximo' => 3000, 'ajustado' => false, 'excede' => true, 'lote' => ['lote' => 'F411250901']])
            ->assertJsonPath('message', 'Hay 3,000 CJA en bodega 006 (lote F411250901); se pedirá igual.');
    }

    public function test_cambiar_de_lote_a_mano(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 3500]);

        // Con el lote grande alcanza; con el chico se mantiene la cantidad y solo se avisa.
        $this->postJson('/pedido/items/lote', ['codigo' => '11715061', 'lote' => 'F411251101'])
            ->assertOk()->assertJson(['cantidad' => 3500, 'maximo' => 3700, 'excede' => false, 'lote' => ['lote' => 'F411251101', 'vence' => '2027-11-24']]);
        $this->postJson('/pedido/items/lote', ['codigo' => '11715061', 'lote' => 'F411250901'])
            ->assertJson(['cantidad' => 3500, 'excede' => true]);
    }

    public function test_lote_inexistente_se_rechaza(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 1]);
        $this->postJson('/pedido/items/lote', ['codigo' => '11715061', 'lote' => 'NO-EXISTE'])->assertStatus(422);
    }

    public function test_pantalla_muestra_selector_con_vencimiento(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 10]);

        $html = $this->get('/pedido')->assertOk()
            ->assertSee('data-articulo-sheet', false)
            ->getContent();

        // La fila muestra el lote elegido (el sugerido) y lleva los lotes para el panel de edición.
        $this->assertStringContainsString('&quot;lote&quot;:&quot;F411250901&quot;,&quot;lotes&quot;', $html);
        $this->assertStringContainsString('&quot;lote&quot;:&quot;F411251101&quot;', $html);
        $this->assertStringContainsString('&quot;vence&quot;:&quot;2027-11-24&quot;', $html);
        $this->assertStringContainsString('01/09/2027', $html);
    }

    public function test_un_solo_lote_se_muestra_fijo(): void
    {
        $this->lotes(['11715061' => [new Lote('UNICO1', 50, '2028-01-31')]]);
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 10]);

        $html = $this->get('/pedido')->getContent();
        $this->assertStringNotContainsString('data-lote-trigger', $html);
        $this->assertStringContainsString('UNICO1', $html);
        $this->assertStringContainsString('31/01/2028', $html);
    }

    public function test_el_pedido_guarda_el_lote_y_se_revalida_al_confirmar(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '11715061', 'cantidad' => 3500]);
        $this->postJson('/pedido/items/lote', ['codigo' => '11715061', 'lote' => 'F411251101']);

        // Entre tanto, el lote elegido se agotó: al confirmar se cambia al sugerido y se pide revisar.
        $this->lotes(['11715061' => [new Lote('F411250901', 3000, '2027-09-01')]]);
        $this->post('/pedidos', [])->assertRedirect(route('carrito.index'))->assertSessionHas('ajustados', ['11715061']);
        $this->assertSame(0, Pedido::count());

        // La cantidad no se recorta (se completa con traslados).
        $this->post('/pedidos', [])->assertRedirect();
        $item = Pedido::sole()->items()->sole();
        $this->assertSame(['F411250901', 3500.0], [$item->lote, $item->cantidad]);
        $this->assertSame('2027-09-01', substr((string) $item->lote_vence, 0, 10));
    }

    public function test_sql_server_lotes_ordena_por_vencimiento_y_filtra(): void
    {
        Schema::create('EXISTENCIA_LOTE', function ($t) {
            $t->string('ARTICULO');
            $t->string('BODEGA');
            $t->string('LOTE');
            $t->decimal('CANT_DISPONIBLE', 14, 2);
        });
        Schema::create('LOTE', function ($t) {
            $t->string('ARTICULO');
            $t->string('LOTE');
            $t->dateTime('FECHA_VENCIMIENTO')->nullable();
            $t->string('ESTADO');
        });
        DB::table('EXISTENCIA_LOTE')->insert([
            ['ARTICULO' => '11715061', 'BODEGA' => '006', 'LOTE' => 'B', 'CANT_DISPONIBLE' => 3700],
            ['ARTICULO' => '11715061', 'BODEGA' => '006', 'LOTE' => 'A', 'CANT_DISPONIBLE' => 3000],
            ['ARTICULO' => '11715061', 'BODEGA' => '006', 'LOTE' => 'VENCIDO', 'CANT_DISPONIBLE' => 99],
            ['ARTICULO' => '11715061', 'BODEGA' => '006', 'LOTE' => 'VACIO', 'CANT_DISPONIBLE' => 0],
            ['ARTICULO' => '11715061', 'BODEGA' => '002', 'LOTE' => 'OTRA', 'CANT_DISPONIBLE' => 500],
        ]);
        DB::table('LOTE')->insert([
            ['ARTICULO' => '11715061', 'LOTE' => 'A', 'FECHA_VENCIMIENTO' => '2027-09-01', 'ESTADO' => 'A'],
            ['ARTICULO' => '11715061', 'LOTE' => 'B', 'FECHA_VENCIMIENTO' => '2027-11-24', 'ESTADO' => 'A'],
            ['ARTICULO' => '11715061', 'LOTE' => 'VENCIDO', 'FECHA_VENCIMIENTO' => '2020-01-01', 'ESTADO' => 'V'],
            ['ARTICULO' => '11715061', 'LOTE' => 'VACIO', 'FECHA_VENCIMIENTO' => '2026-01-01', 'ESTADO' => 'A'],
            ['ARTICULO' => '11715061', 'LOTE' => 'OTRA', 'FECHA_VENCIMIENTO' => '2026-12-01', 'ESTADO' => 'A'],
        ]);

        $res = (new SqlServerLotes(DB::connection(), 'main', '006'))->porArticulos([11715061]); // int a propósito

        $this->assertSame(['A', 'B'], array_map(fn ($l) => $l->lote, $res['11715061']));
        $this->assertSame([3000.0, '2027-09-01'], [$res['11715061'][0]->disponible, $res['11715061'][0]->vence]);
    }
}
