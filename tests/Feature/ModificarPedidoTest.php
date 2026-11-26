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
 * Un pedido guardado en MySQL se puede modificar mientras no se haya enviado a Softland.
 */
class ModificarPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Artículo uno', 'unidad' => 'CJA', 'precio' => 100, 'existencia' => 50],
            ['codigo' => 'B2', 'descripcion' => 'Artículo dos', 'unidad' => 'CJA', 'precio' => 20, 'existencia' => 10],
        ]));
        $this->app->instance(Lotes::class, new DemoLotes(lotes: [
            'A1' => [new Lote('LA-1', 30, '2027-01-31'), new Lote('LA-2', 20, '2027-06-30')],
            'B2' => [new Lote('LB-1', 10, '2027-03-31')],
        ]));
    }

    /** Crea un pedido por el flujo normal: cliente 00003, A1 × 5 (lote LA-2 elegido a mano), observaciones. */
    private function pedidoGuardado(): Pedido
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 5]);
        $this->postJson('/pedido/items/lote', ['codigo' => 'A1', 'lote' => 'LA-2']);
        $this->post('/pedidos', ['notas' => 'ORDEN DE COMPRA No. 1']);

        return Pedido::sole();
    }

    public function test_observaciones_se_guardan_con_el_pedido(): void
    {
        $p = $this->pedidoGuardado();
        $this->assertSame('ORDEN DE COMPRA No. 1', $p->notas);
        $this->get(route('pedidos.show', $p))->assertSee('Observaciones')->assertSee('ORDEN DE COMPRA No. 1')->assertSee('Modificar pedido');
    }

    public function test_modificar_carga_el_pedido_y_guarda_sobre_el_mismo(): void
    {
        $p = $this->pedidoGuardado();

        $this->post(route('pedidos.editar', $p))->assertRedirect(route('carrito.index'));

        // El carrito trae cliente, artículo, el lote que tenía y las observaciones.
        $this->get('/pedido')->assertOk()
            ->assertSee('Modificando pedido')
            ->assertSee($p->folio)
            ->assertSee('Guardar cambios')
            ->assertSee('ORDEN DE COMPRA No. 1')
            ->assertSee('data-seleccionado="LA-2"', false);

        // Cambios: más cantidad, otro artículo y otras observaciones.
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 8]);
        $this->postJson('/pedido/items', ['codigo' => 'B2', 'cantidad' => 2]);
        $this->post('/pedidos', ['notas' => 'ORDEN DE COMPRA No. 2'])
            ->assertRedirect(route('pedidos.show', $p))->assertSessionHas('actualizado');

        $this->assertSame(1, Pedido::count()); // no se creó otro
        $p = $p->fresh('items');
        $this->assertSame(['P-000001', 'ORDEN DE COMPRA No. 2', 2], [$p->folio, $p->notas, $p->lineas]);
        $this->assertEquals(840, $p->total); // 8×100 + 2×20
        $this->assertEqualsCanonicalizing(['A1:8:LA-2', 'B2:2:LB-1'], $p->items->map(fn ($i) => "{$i->codigo}:".(int) $i->cantidad.":{$i->lote}")->all());

        // Al terminar, el siguiente pedido empieza de cero.
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
    }

    public function test_cancelar_edicion_deja_el_pedido_igual(): void
    {
        $p = $this->pedidoGuardado();
        $this->post(route('pedidos.editar', $p));
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1]);

        $this->post(route('pedidos.cancelar-edicion'))->assertRedirect(route('pedidos.show', $p));

        $this->assertEquals(5, $p->fresh('items')->items->sole()->cantidad);
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
    }

    public function test_un_pedido_enviado_a_softland_no_se_puede_modificar(): void
    {
        $p = $this->pedidoGuardado();
        $p->update(['softland_estado' => Pedido::SOFTLAND_ENVIADO, 'softland_pedido' => '00007836']);

        $this->get(route('pedidos.show', $p))->assertDontSee('Modificar pedido');
        $this->post(route('pedidos.editar', $p))->assertRedirect(route('pedidos.show', $p))->assertSessionHas('aviso');
        $this->get('/pedido')->assertRedirect(route('clientes.index')); // no cargó nada
    }

    public function test_si_se_envia_mientras_se_edita_no_se_sobrescribe(): void
    {
        $p = $this->pedidoGuardado();
        $this->post(route('pedidos.editar', $p));
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 9]);

        // Mientras tanto, otro lo envió a Softland.
        $p->update(['softland_estado' => Pedido::SOFTLAND_ENVIADO, 'softland_pedido' => '00007836']);

        $this->post('/pedidos', [])->assertRedirect(route('pedidos.show', $p))->assertSessionHas('aviso');
        $this->assertEquals(5, $p->fresh('items')->items->sole()->cantidad);
    }
}
