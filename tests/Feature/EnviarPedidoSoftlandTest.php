<?php

namespace Tests\Feature;

use App\Catalogo\DemoLotes;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Models\Pedido;
use App\Softland\EnviarPedidoSoftland;
use App\Softland\PedidoSoftland;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Inserción manual en Softland, con Softland simulado en SQLite (esquema "main").
 */
class EnviarPedidoSoftlandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('CLIENTE', function ($t) {
            foreach (['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'DIR_EMB_DEFAULT', 'DIVISION_GEOGRAFICA1', 'DIVISION_GEOGRAFICA2', 'CONDICION_PAGO', 'NIVEL_PRECIO', 'MONEDA_NIVEL'] as $c) {
                $t->string($c)->nullable();
            }
        });
        DB::table('CLIENTE')->insert([
            'CLIENTE' => '03164', 'NOMBRE' => 'DISTRIBUIDORA CRUZ AZUL, S.A. - RUC J0310000016259', 'ALIAS' => 'DISTRIBUIDORA CRUZ AZUL',
            'DIRECCION' => 'DETALLE: BANPRO CIUDAD JARDIN', 'DIR_EMB_DEFAULT' => 'ND', 'DIVISION_GEOGRAFICA1' => 'M', 'DIVISION_GEOGRAFICA2' => 'MANAGUA',
            'CONDICION_PAGO' => '180', 'NIVEL_PRECIO' => 'INST.PUB. C$', 'MONEDA_NIVEL' => 'L',
        ]);
        Schema::create('ARTICULO', fn ($t) => [$t->string('ARTICULO'), $t->string('IMPUESTO'), $t->string('ARTICULO_CUENTA')]);
        Schema::create('IMPUESTO', fn ($t) => [$t->string('IMPUESTO'), $t->decimal('IMPUESTO1', 10, 4)]);
        Schema::create('ARTICULO_CUENTA', fn ($t) => [$t->string('ARTICULO_CUENTA'), $t->string('CTA_VENTAS_LOC'), $t->string('CTR_VENTAS_LOC')]);
        DB::table('ARTICULO')->insert(['ARTICULO' => '11724013', 'IMPUESTO' => 'EXE', 'ARTICULO_CUENTA' => '01']);
        DB::table('IMPUESTO')->insert(['IMPUESTO' => 'EXE', 'IMPUESTO1' => 0]);
        DB::table('ARTICULO_CUENTA')->insert(['ARTICULO_CUENTA' => '01', 'CTA_VENTAS_LOC' => '4.1.01.01.00.00.00', 'CTR_VENTAS_LOC' => '000-00-00']);

        Schema::create('CONSECUTIVO_FA', fn ($t) => [$t->string('CODIGO_CONSECUTIVO'), $t->string('VALOR_CONSECUTIVO'), $t->string('RecordDate')->nullable(), $t->string('UpdatedBy')->nullable()]);
        DB::table('CONSECUTIVO_FA')->insert(['CODIGO_CONSECUTIVO' => 'DESP-A', 'VALOR_CONSECUTIVO' => '00007835']);

        config(['softland.esquema' => 'main']);
        $preparador = new PedidoSoftland(DB::connection(), config('softland'));

        // PEDIDO / PEDIDO_LINEA con exactamente las columnas que se insertan.
        $muestra = $preparador->preparar('X', '03164', [['codigo' => '11724013', 'precio' => 1, 'cantidad' => 1, 'lote' => 'L']], now());
        Schema::create('PEDIDO', function ($t) use ($muestra) {
            foreach (array_keys($muestra['encabezado']) as $c) {
                $t->text($c)->nullable();
            }
        });
        Schema::create('PEDIDO_LINEA', function ($t) use ($muestra) {
            foreach (array_keys($muestra['lineas'][0]) as $c) {
                $t->text($c)->nullable();
            }
        });

        $this->lotes(new Lote('H25124', 1700, '2027-06-30'));
        $this->app->bind(PedidoSoftland::class, fn () => new PedidoSoftland(DB::connection(), config('softland')));
        $this->app->bind(EnviarPedidoSoftland::class, fn ($app) => new EnviarPedidoSoftland(
            DB::connection(), $app->make(PedidoSoftland::class), config('softland'),
        ));
    }

    private function lotes(Lote ...$lotes): void
    {
        $this->app->instance(Lotes::class, new DemoLotes(lotes: ['11724013' => $lotes]));
    }

    private function pedido(float $cantidad = 1700): Pedido
    {
        $p = Pedido::create(['folio' => 'P-000001', 'cliente_codigo' => '03164', 'cliente' => 'DISTRIBUIDORA CRUZ AZUL, S.A.', 'lineas' => 1, 'piezas' => $cantidad, 'total' => 145 * $cantidad]);
        $p->items()->create(['codigo' => '11724013', 'descripcion' => 'Artículo', 'unidad' => 'CJA', 'lote' => 'H25124', 'precio' => 145, 'cantidad' => $cantidad, 'existencia' => 1700, 'subtotal' => 145 * $cantidad]);

        return $p;
    }

    private function enviar(Pedido $p): string
    {
        return app(EnviarPedidoSoftland::class)->enviar($p);
    }

    public function test_inserta_encabezado_y_lineas_y_actualiza_el_consecutivo(): void
    {
        $p = $this->pedido();

        $this->assertSame('00007836', $this->enviar($p));

        $cab = DB::table('PEDIDO')->sole();
        $this->assertSame(['00007836', '03164', 'F12', 'F12', '006', 'CMOREIRA', 'N', 'INST.PUB. C$'],
            [$cab->PEDIDO, $cab->CLIENTE, $cab->VENDEDOR, $cab->COBRADOR, $cab->BODEGA, $cab->USUARIO, $cab->ESTADO, $cab->NIVEL_PRECIO]);
        $this->assertEquals(246500, $cab->TOTAL_A_FACTURAR);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $cab->RowPointer);

        $lin = DB::table('PEDIDO_LINEA')->sole();
        $this->assertSame(['00007836', '1', '11724013', 'H25124', '4.1.01.01.00.00.00'], [$lin->PEDIDO, (string) $lin->PEDIDO_LINEA, $lin->ARTICULO, $lin->LOTE, $lin->CUENTA_CONTABLE]);
        $this->assertEquals(1700, $lin->CANTIDAD_PEDIDA);

        $cons = DB::table('CONSECUTIVO_FA')->sole();
        $this->assertSame(['00007836', 'FA/CMOREIRA'], [$cons->VALOR_CONSECUTIVO, $cons->UpdatedBy]);

        $p->refresh();
        $this->assertSame([Pedido::SOFTLAND_ENVIADO, '00007836', null], [$p->softland_estado, $p->softland_pedido, $p->softland_error]);
        $this->assertNotNull($p->softland_enviado_at);
    }

    public function test_no_se_envia_dos_veces(): void
    {
        $p = $this->pedido();
        $this->enviar($p);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya fue enviado');
        try {
            $this->enviar($p->fresh());
        } finally {
            $this->assertSame(1, DB::table('PEDIDO')->count());
            $this->assertSame('00007836', DB::table('CONSECUTIVO_FA')->value('VALOR_CONSECUTIVO'));
        }
    }

    public function test_lote_sin_existencia_suficiente_se_envia_igual(): void
    {
        // Se completa con traslados antes de facturar: Softland recibe la cantidad pedida.
        $this->lotes(new Lote('H25124', 500, '2027-06-30'));
        $p = $this->pedido(1700);

        $this->assertSame('00007836', $this->enviar($p));
        $this->assertEquals(1700, DB::table('PEDIDO_LINEA')->value('CANTIDAD_PEDIDA'));
        $this->assertSame('H25124', DB::table('PEDIDO_LINEA')->value('LOTE'));
    }

    public function test_si_falla_una_insercion_se_revierte_todo(): void
    {
        Schema::drop('PEDIDO_LINEA'); // la línea fallará después de insertar el encabezado
        $p = $this->pedido();

        try {
            $this->enviar($p);
            $this->fail('Debió fallar.');
        } catch (DomainException $e) {
            $this->assertStringStartsWith('Softland rechazó el pedido:', $e->getMessage());
        }

        $this->assertSame(0, DB::table('PEDIDO')->count()); // el encabezado se revirtió
        $this->assertSame('00007835', DB::table('CONSECUTIVO_FA')->value('VALOR_CONSECUTIVO')); // y el consecutivo
        $this->assertSame(Pedido::SOFTLAND_ERROR, $p->fresh()->softland_estado);
    }

    public function test_observaciones_van_a_softland(): void
    {
        $p = $this->pedido();
        $p->update(['notas' => 'ORDEN DE COMPRA No. 121164']);

        $this->enviar($p);
        $this->assertSame('ORDEN DE COMPRA No. 121164', DB::table('PEDIDO')->value('OBSERVACIONES'));
    }

    /** Simula un envío que se cortó hace $minutos (el proceso murió a mitad). */
    private function trabado(Pedido $p, ?string $numero, int $minutos = 5): Pedido
    {
        Pedido::query()->whereKey($p->id)->toBase()->update([
            'softland_estado' => Pedido::SOFTLAND_ENVIANDO,
            'softland_pedido' => $numero,
            'updated_at' => now()->subMinutes($minutos),
        ]);

        return $p->fresh();
    }

    public function test_envio_trabado_que_si_llego_a_softland_se_marca_enviado(): void
    {
        $p = $this->trabado($this->pedido(), '00007836');
        DB::table('PEDIDO')->insert(['PEDIDO' => '00007836', 'CLIENTE' => '03164']); // sí llegó

        $this->assertTrue(app(EnviarPedidoSoftland::class)->revisarEnvioTrabado($p));
        $this->assertSame([Pedido::SOFTLAND_ENVIADO, '00007836'], [$p->fresh()->softland_estado, $p->fresh()->softland_pedido]);
    }

    public function test_envio_trabado_que_no_llego_muestra_error_y_se_puede_reintentar(): void
    {
        $p = $this->trabado($this->pedido(), '00007836'); // anotó el número pero no llegó a insertar

        $this->get(route('pedidos.show', $p))->assertOk()
            ->assertSee('No se pudo enviar a Softland')
            ->assertSee('El envío anterior se interrumpió y el pedido no llegó a Softland')
            ->assertSee('Reintentar envío a Softland');
        $this->assertSame(Pedido::SOFTLAND_ERROR, $p->fresh()->softland_estado);

        $this->assertSame('00007836', $this->enviar($p->fresh()));
        $this->assertSame(1, DB::table('PEDIDO')->count());
    }

    public function test_envio_reciente_en_curso_no_se_toca(): void
    {
        $p = $this->trabado($this->pedido(), '00007836', minutos: 0);

        $this->assertFalse(app(EnviarPedidoSoftland::class)->revisarEnvioTrabado($p));
        $this->get(route('pedidos.show', $p))->assertSee('Enviando a Softland');
        $this->assertSame(Pedido::SOFTLAND_ENVIANDO, $p->fresh()->softland_estado);
    }

    public function test_salta_un_numero_que_ya_existe(): void
    {
        DB::table('PEDIDO')->insert(['PEDIDO' => '00007836']); // alguien lo usó sin actualizar el consecutivo
        $this->assertSame('00007837', $this->enviar($this->pedido()));
    }

    public function test_pantalla_boton_confirmacion_y_resultado(): void
    {
        $p = $this->pedido();

        $this->get(route('pedidos.show', $p))->assertOk()
            ->assertSee('data-softland-form', false)
            ->assertSee('Enviar a Softland')
            ->assertSee('se asigna al enviar'); // número tentativo
        $this->get('/pedidos')->assertSee('Sin enviar a Softland');

        $this->post(route('pedidos.softland', $p))
            ->assertRedirect(route('pedidos.show', $p))
            ->assertSessionHas('softland_ok', '00007836');

        $this->get(route('pedidos.show', $p))->assertOk()
            ->assertSee('· enviado')
            ->assertSee('N.º 00007836')
            ->assertDontSee('data-softland-form', false);
        $this->get('/pedidos')->assertSee('Softland');

        // Un segundo POST no vuelve a insertar.
        $this->post(route('pedidos.softland', $p))->assertSessionHas('softland_error');
        $this->assertSame(1, DB::table('PEDIDO')->count());
    }
}
