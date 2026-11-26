<?php

namespace Tests\Feature;

use App\Clientes\SqlServerClientes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Mapeo de columnas y búsqueda de clientes usando SQLite como sustituto.
 */
class SqlServerClientesTest extends TestCase
{
    private SqlServerClientes $clientes;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('gmv_clientes', function ($t) {
            foreach (['CLIENTE', 'NOMBRE', 'DIRECCION', 'VENDEDOR', 'RUC', 'ACTIVO', 'MOROSO'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['CREDITO', 'SALDO', 'DISPONIBLE'] as $c) {
                $t->decimal($c, 14, 2)->default(0);
            }
        });
        DB::table('gmv_clientes')->insert([
            ['CLIENTE' => '00003', 'NOMBRE' => 'FARMACIA LA PROVIDENCIA', 'RUC' => '2812304740008U', 'ACTIVO' => 'S', 'MOROSO' => 'N', 'DISPONIBLE' => 2500],
            ['CLIENTE' => '00004', 'NOMBRE' => 'FARMACIA LA UNION', 'RUC' => '2902805650000J', 'ACTIVO' => 'S', 'MOROSO' => 'S', 'DISPONIBLE' => -10],
            ['CLIENTE' => '00099', 'NOMBRE' => 'FARMACIA CERRADA', 'RUC' => 'X', 'ACTIVO' => 'N', 'MOROSO' => 'N', 'DISPONIBLE' => 0],
        ]);

        $this->clientes = new SqlServerClientes(
            db: DB::connection(),
            tabla: 'gmv_clientes',
            columnas: config('clientes.columns'),
            whereRaw: config('clientes.where_raw'),
        );
    }

    public function test_busca_por_nombre_y_excluye_inactivos(): void
    {
        $res = $this->clientes->buscar('farmacia');
        $this->assertSame(['00003', '00004'], array_map(fn ($c) => $c->codigo, $res));
    }

    public function test_busca_por_ruc_y_por_varias_palabras(): void
    {
        $this->assertSame('00004', $this->clientes->buscar('2902805650000J')[0]->codigo);
        $this->assertSame(['00003'], array_map(fn ($c) => $c->codigo, $this->clientes->buscar('la provi')));
    }

    public function test_encontrar_mapea_moroso_y_credito(): void
    {
        $c = $this->clientes->encontrar('00004');
        $this->assertTrue($c->moroso);
        $this->assertSame(-10.0, $c->disponible);
        $this->assertFalse($this->clientes->encontrar('00003')->moroso);
        $this->assertNull($this->clientes->encontrar('00099')); // inactivo
    }

    public function test_ruc_invalido_se_descarta_pero_sigue_siendo_buscable(): void
    {
        DB::table('gmv_clientes')->insert([
            ['CLIENTE' => '03412', 'NOMBRE' => 'FARMACIA EDYLEEN(LEÓN) RUC 2811308840003P', 'RUC' => 'ACIA EDYLEEN(LEÓN) RUC 2811308840003P', 'ACTIVO' => 'S', 'MOROSO' => 'N', 'DISPONIBLE' => 1],
            ['CLIENTE' => '00011', 'NOMBRE' => 'FARMACIA MI FARMACIA', 'RUC' => 'N/D', 'ACTIVO' => 'S', 'MOROSO' => 'N', 'DISPONIBLE' => 1],
        ]);

        $this->assertSame('', $this->clientes->encontrar('03412')->ruc);
        $this->assertSame('', $this->clientes->encontrar('00011')->ruc);
        $this->assertSame('2812304740008U', $this->clientes->encontrar('00003')->ruc);
        $this->assertSame('03412', $this->clientes->buscar('2811308840003P')[0]->codigo);
    }
}
