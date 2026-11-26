<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoFlowTest extends TestCase
{
    use RefreshDatabase;

    private function catalogo(array $filas): void
    {
        $this->app->instance(Catalogo::class, new DemoCatalogo($filas));
    }

    private function fila(string $codigo, float $existencia, float $precio = 10): array
    {
        return ['codigo' => $codigo, 'descripcion' => "Artículo {$codigo}", 'unidad' => 'PZA', 'precio' => $precio, 'existencia' => $existencia];
    }

    /** Clientes demo: 00003 al día (disponible 2,500), 00004 moroso. */
    private function elegirCliente(string $codigo = '00003'): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => $codigo])->assertRedirect(route('articulos.index'));
    }

    // ---------- Paso 1: cliente ----------

    public function test_sin_cliente_el_inicio_lleva_a_elegirlo(): void
    {
        $this->get('/')->assertRedirect(route('clientes.index'));
        $this->get('/clientes')->assertOk()->assertSee('Busca el cliente');
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
        $this->get('/pedido')->assertRedirect(route('clientes.index'));
        $this->postJson('/pedido/items', ['codigo' => 'X', 'cantidad' => 1])->assertStatus(409);
        $this->get('/articulos/buscar?q=to', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(409)->assertHeader('X-Redirect', route('clientes.index'));
    }

    public function test_busqueda_de_clientes_muestra_moroso_y_credito(): void
    {
        $this->get('/clientes/buscar?q=farmacia')
            ->assertOk()
            ->assertSee('FARMACIA LA PROVIDENCIA')
            ->assertSee('FARMACIA LA UNION')
            ->assertSee('Moroso')
            ->assertSeeText('Disponible C$2,500.00');

        $this->get('/clientes/buscar?q=hospital')->assertSee('Sin crédito disponible');
        $this->get('/clientes/buscar?q=00005')->assertSee('FARMACIA LOPEZ')->assertDontSee('PROVIDENCIA');
    }

    public function test_elegir_cliente_abre_articulos_con_el_cliente_visible(): void
    {
        $this->elegirCliente('00004');

        $this->get('/')->assertRedirect(route('articulos.index'));
        $this->get('/articulos')->assertOk()
            ->assertSee('Cliente del pedido')
            ->assertSee('FARMACIA LA UNION')
            ->assertSee('Moroso');
    }

    public function test_solo_clientes_del_vendedor_configurado(): void
    {
        $this->get('/clientes')->assertSee('Clientes de Cesia Moreira (F12)');
        $this->get('/clientes/buscar?q=farmacia')->assertSee('FARMACIA LOPEZ')->assertDontSee('OTRO VENDEDOR');

        $this->from('/clientes')->post('/clientes/seleccionar', ['codigo' => '00020'])
            ->assertRedirect('/clientes')->assertSessionHas('aviso');
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
    }

    public function test_cliente_inexistente_no_se_puede_elegir(): void
    {
        $this->from('/clientes')->post('/clientes/seleccionar', ['codigo' => 'NOPE'])
            ->assertRedirect('/clientes')->assertSessionHas('aviso');
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
    }

    // ---------- Paso 2: artículos ----------

    public function test_pantallas_con_cliente_cargan(): void
    {
        $this->elegirCliente();
        $this->get('/articulos')->assertOk()->assertSee('Busca un artículo');
        $this->get('/pedido')->assertOk()->assertSee('Tu pedido está vacío');
        $this->get('/pedidos')->assertOk()->assertSee('Aún no hay pedidos');
    }

    public function test_busqueda_muestra_precio_y_existencia(): void
    {
        $this->elegirCliente();
        $this->get('/articulos/buscar?q=tornillo galv')
            ->assertOk()
            ->assertSee('TOR-0001')
            ->assertSee('$1.85')
            ->assertSeeText('1,250 PZA disp.')
            ->assertDontSee('TUE-0010');
    }

    public function test_agotados_se_marcan(): void
    {
        $this->elegirCliente();
        $this->get('/articulos/buscar?q=RON-0020')->assertSee('Agotado');
    }

    public function test_cantidad_se_limita_a_la_existencia(): void
    {
        $this->catalogo([$this->fila('A1', 5)]);
        $this->elegirCliente();

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 8])
            ->assertOk()
            ->assertJson([
                'cantidad' => 5,
                'maximo' => 5,
                'ajustado' => true,
                'subtotal' => 50,
                'resumen' => ['lineas' => 1, 'piezas' => 5, 'total' => 50],
            ]);
    }

    public function test_no_se_puede_pedir_un_agotado(): void
    {
        $this->catalogo([$this->fila('A1', 0)]);
        $this->elegirCliente();

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1])
            ->assertJson(['cantidad' => 0, 'resumen' => ['lineas' => 0]]);
    }

    public function test_cantidad_cero_quita_el_articulo(): void
    {
        $this->catalogo([$this->fila('A1', 5)]);
        $this->elegirCliente();
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 2]);

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 0])
            ->assertJson(['cantidad' => 0, 'resumen' => ['lineas' => 0]]);
    }

    public function test_articulo_inexistente_responde_404(): void
    {
        $this->catalogo([]);
        $this->elegirCliente();
        $this->postJson('/pedido/items', ['codigo' => 'X', 'cantidad' => 1])->assertNotFound();
    }

    // ---------- Paso 3: pedido ----------

    public function test_confirmar_pedido_guarda_cliente_y_reinicia_el_flujo(): void
    {
        $this->catalogo([$this->fila('A1', 10, 2.5), $this->fila('B2', 3, 100)]);
        $this->elegirCliente('00003');
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 4]);
        $this->postJson('/pedido/items', ['codigo' => 'B2', 'cantidad' => 3]);

        $res = $this->post('/pedidos', ['contacto' => 'Lic. Pérez', 'notas' => 'Entregar lunes']);

        $pedido = Pedido::with('items')->sole();
        $res->assertRedirect(route('pedidos.show', $pedido));
        $this->assertSame('P-000001', $pedido->folio);
        $this->assertSame('00003', $pedido->cliente_codigo);
        $this->assertSame('FARMACIA LA PROVIDENCIA', $pedido->cliente);
        $this->assertSame('J0310000000003', $pedido->cliente_ruc);
        $this->assertEquals(2500, $pedido->credito_disponible);
        $this->assertSame(2, $pedido->lineas);
        $this->assertEquals(310, $pedido->total);
        $this->assertEquals([4, 3], $pedido->items->pluck('cantidad')->all());

        $this->get(route('pedidos.show', $pedido))->assertSee('FARMACIA LA PROVIDENCIA')->assertSee('$310.00');
        $this->get('/pedidos?q=00003')->assertSee('P-000001');

        // El siguiente pedido empieza otra vez eligiendo cliente.
        $this->get('/articulos')->assertRedirect(route('clientes.index'));
    }

    public function test_aviso_de_credito_no_bloquea(): void
    {
        $this->catalogo([$this->fila('A1', 100, 100)]);
        $this->elegirCliente('00003'); // disponible 2,500
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 30]); // 3,000

        $html = $this->get('/pedido')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<p data-credit-warning data-disponible="2500"\s+class/', $html);

        $this->post('/pedidos', [])->assertRedirect();
        $this->assertSame(1, Pedido::count());
        $this->get(route('pedidos.show', Pedido::sole()))->assertSee('superaba el crédito disponible');
    }

    public function test_aviso_de_credito_oculto_si_alcanza(): void
    {
        $this->catalogo([$this->fila('A1', 100, 10)]);
        $this->elegirCliente('00003');
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 3]);

        $this->assertMatchesRegularExpression('/data-credit-warning data-disponible="2500"\s+hidden/', $this->get('/pedido')->getContent());
    }

    public function test_si_el_cliente_se_desactiva_antes_de_confirmar(): void
    {
        $this->catalogo([$this->fila('A1', 10)]);
        $this->elegirCliente('00003');
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1]);

        $this->app->instance(Clientes::class, new DemoClientes([]));

        $this->post('/pedidos', [])->assertRedirect(route('clientes.index'))->assertSessionHas('aviso');
        $this->assertSame(0, Pedido::count());
    }

    public function test_si_la_existencia_baja_antes_de_confirmar_se_ajusta_y_no_se_guarda(): void
    {
        $this->catalogo([$this->fila('A1', 10)]);
        $this->elegirCliente();
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 8]);

        // Alguien más compró: ahora solo hay 3.
        $this->catalogo([$this->fila('A1', 3)]);

        $this->post('/pedidos', [])
            ->assertRedirect(route('carrito.index'))
            ->assertSessionHas('aviso')
            ->assertSessionHas('ajustados', ['A1']);

        $this->assertSame(0, Pedido::count());

        // Segundo intento con la cantidad ya ajustada: sí se guarda.
        $this->post('/pedidos', [])->assertRedirect();
        $this->assertSame(1, Pedido::count());
        $this->assertEquals(3, Pedido::sole()->piezas);
    }
}
