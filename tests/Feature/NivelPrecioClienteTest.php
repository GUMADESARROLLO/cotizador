<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El pedido usa CONDICION_PAGO y NIVEL_PRECIO del cliente; la moneda de los
 * precios es la del nivel (L = C$, D = US$).
 */
class NivelPrecioClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Artículo uno', 'unidad' => 'CJA', 'precio' => 100, 'existencia' => 50],
        ]));
    }

    public function test_el_nivel_del_cliente_se_aplica_al_catalogo(): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00030']); // INST.PRIV.U$

        $this->get('/articulos')->assertOk();
        $this->assertSame('INST.PRIV.U$', config('articulos.precio.nivel'));
        $this->assertSame('US$', config('articulos.currency'));
    }

    public function test_cliente_en_dolares_ve_us_y_el_pedido_guarda_moneda_y_nivel(): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00030']);
        $this->get('/articulos')->assertSee('Precios INST.PRIV.U$')->assertSee('Condición');
        $this->get('/articulos/buscar?q=uno')->assertSee('US$100.00');

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 2]);
        $this->post('/pedidos', [])->assertRedirect();

        $p = Pedido::sole();
        $this->assertSame(['INST.PRIV.U$', 'D', '180'], [$p->nivel_precio, $p->moneda, $p->condicion_pago]);
        $this->get(route('pedidos.show', $p))->assertSee('US$200.00')->assertSee('INST.PRIV.U$');
        $this->get('/pedidos')->assertSee('US$200.00');
    }

    public function test_cliente_en_cordobas_sigue_en_cordobas(): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00003']); // INST.PUB. C$
        $this->get('/articulos/buscar?q=uno')->assertSee('C$100.00')->assertDontSee('US$');
    }

    public function test_sin_aviso_de_credito_si_precio_y_credito_tienen_distinta_moneda(): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00031']); // precios C$, crédito US$
        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 50]); // C$5,000

        $html = $this->get('/pedido')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-credit-warning', $html);
        $this->assertStringContainsString('US$9,000.00', $html); // disponible en la moneda del cliente

        $this->post('/pedidos', []);
        $this->assertNull(Pedido::sole()->credito_disponible);
    }
}
