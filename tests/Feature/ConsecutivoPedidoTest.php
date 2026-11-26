<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Softland\ConsecutivoPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsecutivoPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_incrementa_la_parte_numerica_respetando_la_mascara(): void
    {
        $this->assertSame('00007836', ConsecutivoPedido::incrementar('00007835'));
        $this->assertSame('PTV7-001129', ConsecutivoPedido::incrementar('PTV7-001128'));
        $this->assertSame('PDX10-000254', ConsecutivoPedido::incrementar('PDX10-000253'));
        $this->assertSame('P-010000', ConsecutivoPedido::incrementar('P-009999'));
        $this->assertSame('PLIC-000001', ConsecutivoPedido::incrementar('PLIC-000000'));
    }

    public function test_lee_el_ultimo_valor_y_devuelve_el_siguiente(): void
    {
        Schema::create('CONSECUTIVO_FA', function ($t) {
            $t->string('CODIGO_CONSECUTIVO');
            $t->string('VALOR_CONSECUTIVO');
        });
        DB::table('CONSECUTIVO_FA')->insert([
            ['CODIGO_CONSECUTIVO' => 'DESP-A', 'VALOR_CONSECUTIVO' => '00007835'],
            ['CODIGO_CONSECUTIVO' => 'PTV07', 'VALOR_CONSECUTIVO' => 'PTV7-001128'],
        ]);

        // En SQLite el "esquema" es el prefijo de la base attach-eada; aquí usamos main.
        $c = new ConsecutivoPedido(DB::connection(), 'main', 'DESP-A');
        $this->assertSame('00007836', $c->siguiente());

        $this->assertNull((new ConsecutivoPedido(DB::connection(), 'main', 'NO-EXISTE'))->siguiente());
    }

    public function test_el_pedido_muestra_el_numero_tentativo(): void
    {
        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Artículo', 'unidad' => 'CJA', 'precio' => 10, 'existencia' => 5],
        ]));
        $this->app->instance(ConsecutivoPedido::class, new class extends ConsecutivoPedido
        {
            public function __construct() {}

            public function codigo(): string
            {
                return 'DESP-A';
            }

            public function siguiente(): ?string
            {
                return '00007836';
            }
        });

        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
        $this->get('/pedido')->assertDontSee('Pedido Softland'); // vacío: no se muestra

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 1]);
        $this->get('/pedido')->assertOk()
            ->assertSee('Pedido Softland')
            ->assertSee('N.º 00007836')
            ->assertSee('Consecutivo <b class="text-ink">DESP-A</b>', false);
    }
}
