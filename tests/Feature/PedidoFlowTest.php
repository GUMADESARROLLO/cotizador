<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
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

    public function test_pantallas_principales_cargan(): void
    {
        $this->get('/')->assertOk()->assertSee('Busca un artículo');
        $this->get('/pedido')->assertOk()->assertSee('Tu pedido está vacío');
        $this->get('/pedidos')->assertOk()->assertSee('Aún no hay pedidos');
    }

    public function test_busqueda_muestra_precio_y_existencia(): void
    {
        $this->get('/articulos/buscar?q=tornillo galv')
            ->assertOk()
            ->assertSee('TOR-0001')
            ->assertSee('$1.85')
            ->assertSeeText('1,250 PZA disp.')
            ->assertDontSee('TUE-0010');
    }

    public function test_agotados_se_marcan(): void
    {
        $this->get('/articulos/buscar?q=RON-0020')->assertSee('Agotado');
    }

    public function test_cantidad_se_limita_a_la_existencia(): void
    {
        $this->catalogo([$this->fila('A1', 5)]);

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

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1])
            ->assertJson(['cantidad' => 0, 'resumen' => ['lineas' => 0]]);
    }

    public function test_cantidad_cero_quita_el_articulo(): void
    {
        $this->catalogo([$this->fila('A1', 5)]);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 2]);

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 0])
            ->assertJson(['cantidad' => 0, 'resumen' => ['lineas' => 0]]);
    }

    public function test_articulo_inexistente_responde_404(): void
    {
        $this->catalogo([]);
        $this->postJson('/pedido/items', ['codigo' => 'X', 'cantidad' => 1])->assertNotFound();
    }

    public function test_confirmar_pedido_guarda_en_mysql_y_vacia_el_carrito(): void
    {
        $this->catalogo([$this->fila('A1', 10, 2.5), $this->fila('B2', 3, 100)]);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 4]);
        $this->postJson('/pedido/items', ['codigo' => 'B2', 'cantidad' => 3]);

        $res = $this->post('/pedidos', ['cliente' => 'Ferretería Centro', 'notas' => 'Entregar lunes']);

        $pedido = Pedido::with('items')->sole();
        $res->assertRedirect(route('pedidos.show', $pedido));
        $this->assertSame('P-000001', $pedido->folio);
        $this->assertSame(2, $pedido->lineas);
        $this->assertEquals(7, $pedido->piezas);
        $this->assertEquals(310, $pedido->total);
        $this->assertEquals([4, 3], $pedido->items->pluck('cantidad')->all());

        $this->get('/pedido')->assertSee('Tu pedido está vacío');
        $this->get(route('pedidos.show', $pedido))->assertSee('Ferretería Centro')->assertSee('$310.00');
    }

    public function test_si_la_existencia_baja_antes_de_confirmar_se_ajusta_y_no_se_guarda(): void
    {
        $this->catalogo([$this->fila('A1', 10)]);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 8]);

        // Alguien más compró: ahora solo hay 3.
        $this->catalogo([$this->fila('A1', 3)]);

        $this->post('/pedidos', ['cliente' => 'Cliente'])
            ->assertRedirect(route('carrito.index'))
            ->assertSessionHas('aviso')
            ->assertSessionHas('ajustados', ['A1']);

        $this->assertSame(0, Pedido::count());
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 3])->assertJson(['cantidad' => 3]);

        // Segundo intento con la cantidad ya ajustada: sí se guarda.
        $this->post('/pedidos', ['cliente' => 'Cliente'])->assertRedirect();
        $this->assertSame(1, Pedido::count());
    }

    public function test_cliente_es_obligatorio(): void
    {
        $this->catalogo([$this->fila('A1', 10)]);
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1]);

        $this->post('/pedidos', [])->assertSessionHasErrors('cliente');
    }
}
