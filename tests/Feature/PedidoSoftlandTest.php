<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Softland\ConsecutivoPedido;
use App\Softland\PedidoSoftland;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Armado (sin insertar) del encabezado de Softland.umk.PEDIDO.
 */
class PedidoSoftlandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ficha de cliente como en umk.CLIENTE (en SQLite el esquema es "main").
        Schema::create('CLIENTE', function ($t) {
            foreach (['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'DIR_EMB_DEFAULT', 'DIVISION_GEOGRAFICA1', 'DIVISION_GEOGRAFICA2', 'CONDICION_PAGO', 'NIVEL_PRECIO', 'MONEDA_NIVEL', 'COBRADOR', 'ZONA', 'PAIS'] as $c) {
                $t->string($c)->nullable();
            }
        });
        DB::table('CLIENTE')->insert([
            'CLIENTE' => '00003', 'NOMBRE' => 'FARMACIA LA PROVIDENCIA - RUC J0310000000003', 'ALIAS' => 'FARMACIA LA PROVIDENCIA',
            'DIRECCION' => 'DETALLE: BANPRO SUBTIAVA', 'DIR_EMB_DEFAULT' => 'ND', 'DIVISION_GEOGRAFICA1' => 'L', 'DIVISION_GEOGRAFICA2' => 'LEON',
            'CONDICION_PAGO' => '180', 'NIVEL_PRECIO' => 'INST.PUB. C$', 'MONEDA_NIVEL' => 'L',
            // en la ficha vienen otros valores que el pedido NO debe copiar:
            'COBRADOR' => 'F02', 'ZONA' => '01', 'PAIS' => '505',
        ]);

        // Artículos con su impuesto y cuenta contable (umk.ARTICULO / IMPUESTO / ARTICULO_CUENTA).
        Schema::create('ARTICULO', function ($t) {
            $t->string('ARTICULO');
            $t->string('IMPUESTO');
            $t->string('ARTICULO_CUENTA');
        });
        Schema::create('IMPUESTO', function ($t) {
            $t->string('IMPUESTO');
            $t->decimal('IMPUESTO1', 10, 4);
        });
        Schema::create('ARTICULO_CUENTA', function ($t) {
            $t->string('ARTICULO_CUENTA');
            $t->string('CTA_VENTAS_LOC');
            $t->string('CTR_VENTAS_LOC');
        });
        DB::table('ARTICULO')->insert([
            ['ARTICULO' => 'A1', 'IMPUESTO' => 'EXE', 'ARTICULO_CUENTA' => '01'],
            ['ARTICULO' => 'B2', 'IMPUESTO' => '01', 'ARTICULO_CUENTA' => '18'],
        ]);
        DB::table('IMPUESTO')->insert([['IMPUESTO' => 'EXE', 'IMPUESTO1' => 0], ['IMPUESTO' => '01', 'IMPUESTO1' => 15]]);
        DB::table('ARTICULO_CUENTA')->insert([
            ['ARTICULO_CUENTA' => '01', 'CTA_VENTAS_LOC' => '4.1.01.01.00.00.00', 'CTR_VENTAS_LOC' => '000-00-00'],
            ['ARTICULO_CUENTA' => '18', 'CTA_VENTAS_LOC' => '4.1.01.01.00.00.00', 'CTR_VENTAS_LOC' => '612-00-00'],
        ]);

        config(['softland.esquema' => 'main']);
        $this->app->bind(PedidoSoftland::class, fn () => new PedidoSoftland(DB::connection(), config('softland')));
    }

    /** A1 exento: 145 × 1,700 · B2 con IVA 15 %: 10.50 × 2 */
    private function preparar(): array
    {
        return app(PedidoSoftland::class)->preparar('00007836', '00003', [
            'A1' => ['codigo' => 'A1', 'precio' => 145, 'cantidad' => 1700, 'lote' => 'H25124'],
            'B2' => ['codigo' => 'B2', 'precio' => 10.5, 'cantidad' => 2, 'lote' => 'L-B2'],
        ], Carbon::parse('2026-11-26 10:15:30'));
    }

    private function encabezado(): array
    {
        return array_map(fn ($x) => $x['valor'], $this->preparar()['encabezado']);
    }

    public function test_impuesto_por_articulo_y_totales(): void
    {
        $e = $this->encabezado();

        $this->assertSame(246521.0, $e['TOTAL_MERCADERIA']);
        $this->assertSame(3.15, $e['TOTAL_IMPUESTO1']); // 15 % de 21.00 (solo B2)
        $this->assertSame(21.0, $e['BASE_IMPUESTO1']);
        $this->assertSame(246524.15, $e['TOTAL_A_FACTURAR']);
    }

    public function test_lineas_con_lote_cuenta_y_porcentaje(): void
    {
        $lineas = array_map(fn ($l) => array_map(fn ($x) => $x['valor'], $l), $this->preparar()['lineas']);

        [$a, $b] = $lineas;
        $this->assertSame([1, 0, 'A1', 'H25124', '006', 'N'], [$a['PEDIDO_LINEA'], $a['LINEA_USUARIO'], $a['ARTICULO'], $a['LOTE'], $a['BODEGA'], $a['ESTADO']]);
        $this->assertSame([145.0, 1700.0, 1700.0], [$a['PRECIO_UNITARIO'], $a['CANTIDAD_PEDIDA'], $a['CANTIDAD_A_FACTURA']]);
        $this->assertSame(['4.1.01.01.00.00.00', '000-00-00', 0.0, 0.0], [$a['CUENTA_CONTABLE'], $a['CENTRO_COSTO'], $a['PORC_IMPUESTO1'], $a['PORC_IMP1_BASE']]);

        $this->assertSame([2, 1, 'B2'], [$b['PEDIDO_LINEA'], $b['LINEA_USUARIO'], $b['ARTICULO']]);
        $this->assertSame(['612-00-00', 15.0, 15.0], [$b['CENTRO_COSTO'], $b['PORC_IMPUESTO1'], $b['PORC_IMP1_BASE']]);
        $this->assertSame(['00007836', 'FA/CMOREIRA', 'NEWID()'], [$b['PEDIDO'], $b['CreatedBy'], $b['RowPointer']]);
    }

    public function test_reglas_del_encabezado(): void
    {
        $e = $this->encabezado();

        // Fijos / configuración
        $this->assertSame(['N', 'N', 'F', 'P', 'FAC', 0], [$e['ESTADO'], $e['AUTORIZADO'], $e['ORIGEN_PEDIDO'], $e['TIPO_DOCUMENTO'], $e['TIPO_DOC_CXC'], $e['SUBTIPO_DOC_CXC']]);
        $this->assertSame(['F12', 'F12', 'ND', 'ND', 'NI', '006', 'CMOREIRA', 'FA/CMOREIRA'],
            [$e['VENDEDOR'], $e['COBRADOR'], $e['RUTA'], $e['ZONA'], $e['PAIS'], $e['BODEGA'], $e['USUARIO'], $e['CreatedBy']]);

        // Copiados de la ficha del cliente
        $this->assertSame(['00003', '00003', '00003', '00003'], [$e['CLIENTE'], $e['CLIENTE_DIRECCION'], $e['CLIENTE_CORPORAC'], $e['CLIENTE_ORIGEN']]);
        $this->assertSame('FARMACIA LA PROVIDENCIA - RUC J0310000000003', $e['NOMBRE_CLIENTE']);
        $this->assertSame('FARMACIA LA PROVIDENCIA', $e['EMBARCAR_A']); // alias
        $this->assertSame('DETALLE: BANPRO SUBTIAVA', $e['DIRECCION_FACTURA']); // tal cual en Softland
        $this->assertSame(['180', 'INST.PUB. C$', 'L', 'L', 'L', 'LEON'], [$e['CONDICION_PAGO'], $e['NIVEL_PRECIO'], $e['MONEDA'], $e['MONEDA_PEDIDO'], $e['DIVISION_GEOGRAFICA1'], $e['DIVISION_GEOGRAFICA2']]);

        // Totales y fechas
        $this->assertEquals(1702, $e['TOTAL_UNIDADES']);
        $this->assertSame('2026-11-26 00:00:00.000', $e['FECHA_PEDIDO']);
        $this->assertSame('2026-11-26 10:15:30.000', $e['FECHA_HORA']);
        $this->assertSame('00007836', $e['PEDIDO']);
    }

    public function test_cliente_en_dolares_lleva_moneda_d(): void
    {
        DB::table('CLIENTE')->update(['NIVEL_PRECIO' => 'INST.PRIV.U$', 'MONEDA_NIVEL' => 'D']);
        $e = $this->encabezado();
        $this->assertSame(['INST.PRIV.U$', 'D', 'D'], [$e['NIVEL_PRECIO'], $e['MONEDA'], $e['MONEDA_PEDIDO']]);
    }

    public function test_vista_previa_muestra_los_campos_sin_insertar(): void
    {
        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Artículo', 'unidad' => 'CJA', 'precio' => 145, 'existencia' => 5000],
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
        $this->get('/pedido/softland')->assertRedirect(route('carrito.index')); // sin artículos

        $this->postJson('/pedido/items', ['codigo' => 'A1', 'cantidad' => 10]);
        $this->get('/pedido')->assertSee('Ver datos para Softland');
        $this->get('/pedido/softland')->assertOk()
            ->assertSee('no se envía a Softland', false)
            ->assertSee('NOMBRE_CLIENTE')
            ->assertSee('00007836')
            ->assertSee('FARMACIA LA PROVIDENCIA')
            ->assertSee('umk.PEDIDO_LINEA · línea 1 · A1')
            ->assertSee('CUENTA_CONTABLE')
            ->assertSee('4.1.01.01.00.00.00')
            ->assertDontSee('PENDIENTE');
    }
}
