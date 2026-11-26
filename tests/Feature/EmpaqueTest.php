<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\DemoLotes;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unidades vs. cajas (FACTOR_EMPAQUE) y precio manual, como el pedido 00007836:
 * piden 2,800 viales, la caja trae 50 → 56 CJA; precio C$32.93 por vial × 50
 * = C$1,646.50 por caja.
 */
class EmpaqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => '13705183', 'descripcion' => 'Vial', 'unidad' => 'CJA', 'precio' => 1, 'existencia' => 100.26, 'factor' => 50, 'unidad_empaque' => 'VIA'],
            ['codigo' => 'SINPRE', 'descripcion' => 'Sin precio en el nivel', 'unidad' => 'CJA', 'precio' => 0, 'existencia' => 10],
            ['codigo' => 'SIMPLE', 'descripcion' => 'Sin empaque', 'unidad' => 'CJA', 'precio' => 20, 'existencia' => 10],
        ]));
        $this->app->instance(Lotes::class, new DemoLotes(lotes: [
            '13705183' => [new Lote('L1', 100.26, '2027-09-01')],
        ]));

        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
    }

    public function test_2800_viales_son_56_cajas_y_se_muestra_lo_ingresado(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad'])
            ->assertOk()
            ->assertJson([
                'modo' => 'unidad', 'cantidad' => 2800, 'cajas' => 56, 'unidades' => 2800,
                'capturada_texto' => '2,800 VIA', 'equivalencia' => '= 56 CJA',
            ]);

        // En la fila: cajas como cantidad principal y las unidades de referencia.
        $this->get('/pedido')->assertOk()
            ->assertSee('56 <span class="text-[15px]">CJA</span>', false)
            ->assertSee('2,800 VIA')
            ->assertSee('data-articulo-sheet', false);
    }

    public function test_fraccion_de_caja_y_tope_en_unidades(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2810, 'modo' => 'unidad'])
            ->assertJson(['cajas' => 56.2, 'equivalencia' => '= 56.2 CJA']);

        // 100.26 CJA × 50 = 5,013 viales disponibles: se pueden pedir más (solo se avisa).
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 9999, 'modo' => 'unidad'])
            ->assertJson(['cantidad' => 9999, 'maximo' => 5013, 'ajustado' => false, 'excede' => true, 'cajas' => 199.98])
            ->assertJsonPath('message', 'Hay 100.26 CJA en bodega 006 (lote L1); se pedirá igual.');
    }

    public function test_cambiar_a_cajas_conserva_lo_pedido(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad']);

        $this->postJson('/pedido/items', ['codigo' => '13705183', 'modo' => 'caja'])
            ->assertJson(['modo' => 'caja', 'cantidad' => 56, 'capturada_texto' => '56 CJA', 'equivalencia' => '= 2,800 VIA']);

        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 10.5, 'modo' => 'caja'])
            ->assertJson(['cajas' => 10.5, 'unidades' => 525]);
    }

    public function test_precio_manual_por_unidad(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad']);

        $this->postJson('/pedido/items/precio', ['codigo' => '13705183', 'precio' => 32.93, 'por' => 'unidad'])
            ->assertOk()
            ->assertJson([
                'precio' => 1646.5, 'precio_unidad' => 32.93, 'precio_manual' => true,
                'subtotal' => 92204, 'precio_texto' => 'C$32.93 / VIA · C$1,646.50 / CJA',
            ])
            ->assertJsonPath('resumen.total', 92204);

        // Cambiar la cantidad no pisa el precio manual.
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2810, 'modo' => 'unidad'])
            ->assertJson(['precio' => 1646.5, 'subtotal' => 92533.3]);

        // Volver al precio del nivel.
        $this->postJson('/pedido/items/precio', ['codigo' => '13705183', 'precio' => null])
            ->assertJson(['precio' => 1, 'precio_manual' => false]);
    }

    public function test_precio_debe_ser_mayor_que_cero(): void
    {
        $this->postJson('/pedido/items', ['codigo' => 'SIMPLE', 'cantidad' => 1]);
        $this->postJson('/pedido/items/precio', ['codigo' => 'SIMPLE', 'precio' => 0])->assertStatus(422);
    }

    public function test_sin_precio_en_el_nivel_se_agrega_pero_no_se_confirma_hasta_ponerlo(): void
    {
        $this->get('/articulos/buscar?q=nivel')->assertSee('Sin precio')->assertDontSee('Sin precio para este cliente');

        $this->postJson('/pedido/items', ['codigo' => 'SINPRE', 'cantidad' => 2])->assertOk()->assertJson(['sin_precio' => true]);
        $this->get('/pedido')->assertSee('Falta precio');

        $this->post('/pedidos', [])->assertRedirect(route('carrito.index'))->assertSessionHas('aviso', 'Pon el precio de 1 artículo(s) antes de confirmar.');
        $this->assertSame(0, Pedido::count());

        $this->postJson('/pedido/items/precio', ['codigo' => 'SINPRE', 'precio' => 15, 'por' => 'caja']);
        $this->post('/pedidos', []);
        $this->assertSame(30.0, Pedido::sole()->total);
    }

    public function test_se_guarda_con_unidades_y_precio_manual_y_se_recupera_al_modificar(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad']);
        $this->postJson('/pedido/items/precio', ['codigo' => '13705183', 'precio' => 32.93, 'por' => 'unidad']);
        $this->post('/pedidos', [])->assertRedirect();

        $p = Pedido::sole();
        $i = $p->items->sole();
        $this->assertSame([56.0, 2800.0, 'unidad', 50.0, 'VIA', 1646.5, true], [$i->cantidad, $i->unidades, $i->modo, $i->factor, $i->unidad_empaque, $i->precio, $i->precio_manual]);
        $this->assertSame(92204.0, $p->total);

        $this->get(route('pedidos.show', $p))->assertSee('2,800 VIA')->assertSee('C$32.93')->assertDontSee('Precio manual');

        // Al modificar, vuelve en unidades y con su precio manual (sin marcarlo como "cambió").
        $this->post(route('pedidos.editar', $p))->assertSessionHas('ajustados', []);
        $this->get('/pedido')->assertSee('2,800 VIA')->assertSee('/ 2,800 VIA')->assertSee('C$1,646.50')->assertSee('C$32.93')->assertDontSee('>Manual<', false);
    }

    public function test_sin_empaque_sigue_en_cajas(): void
    {
        $this->postJson('/pedido/items', ['codigo' => 'SIMPLE', 'cantidad' => 3, 'modo' => 'unidad'])
            ->assertJson(['modo' => 'caja', 'cantidad' => 3, 'equivalencia' => null, 'subtotal' => 60]);
    }

    public function test_por_defecto_se_captura_en_cajas(): void
    {
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 1])
            ->assertJson(['modo' => 'caja', 'cajas' => 1, 'unidades' => 50]);
    }

    public function test_editar_cantidad_precio_y_lote_en_un_paso(): void
    {
        $this->app->instance(Lotes::class, new DemoLotes(lotes: [
            '13705183' => [new Lote('L1', 10, '2027-09-01'), new Lote('L2', 90.26, '2027-12-01')],
        ]));
        $this->postJson('/pedido/items', ['codigo' => '13705183', 'cantidad' => 1]);

        $r = $this->postJson('/pedido/items/editar', [
            'codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad', 'lote' => 'L2', 'precio' => 32.93, 'por' => 'unidad',
        ])->assertOk()->assertJson(['ajustado' => false])->assertJsonPath('resumen.total', 92204);

        $this->assertStringContainsString('2,800 VIA', $r->json('html'));
        $this->assertStringContainsString('L2', $r->json('html'));

        // Lote con menos existencia: se mantiene la cantidad y solo se avisa.
        $this->postJson('/pedido/items/editar', ['codigo' => '13705183', 'cantidad' => 2800, 'modo' => 'unidad', 'lote' => 'L1'])
            ->assertJson(['ajustado' => false, 'excede' => true])
            ->assertJsonPath('message', 'Hay 10 CJA en bodega 006 (lote L1); se pedirá igual.');

        // Sin tocar el precio, se conserva el manual; precio_nivel lo devuelve.
        $this->postJson('/pedido/items/editar', ['codigo' => '13705183', 'cantidad' => 5, 'modo' => 'caja', 'lote' => 'L1', 'precio_nivel' => true])
            ->assertJsonPath('resumen.total', 5);

        // Lote inexistente y cantidad 0 (quitar).
        $this->postJson('/pedido/items/editar', ['codigo' => '13705183', 'cantidad' => 1, 'lote' => 'NO'])->assertStatus(422);
        $this->postJson('/pedido/items/editar', ['codigo' => '13705183', 'cantidad' => 0])->assertJson(['eliminado' => true]);
        $this->assertSame(0, $this->postJson('/pedido/items', ['codigo' => 'SIMPLE', 'cantidad' => 0])->json('resumen.lineas'));
    }
}
