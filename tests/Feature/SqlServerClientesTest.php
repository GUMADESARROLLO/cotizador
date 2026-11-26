<?php

namespace Tests\Feature;

use App\Clientes\SqlServerClientes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lectura de clientes como en Softland.umk.CLIENTE, usando SQLite como sustituto.
 */
class SqlServerClientesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cliente', function ($t) {
            foreach (['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'VENDEDOR', 'ACTIVO', 'MOROSO'] as $c) {
                $t->string($c)->nullable();
            }
            $t->string('CONDICION_PAGO')->default('180');
            $t->string('NIVEL_PRECIO')->default('INST.PUB. C$');
            $t->string('MONEDA_NIVEL')->default('L');
            $t->string('MONEDA')->default('COR');
            $t->decimal('LIMITE_CREDITO', 14, 2)->default(0);
            $t->decimal('SALDO', 14, 2)->default(0);
        });

        $f = fn ($cod, $nombre, $vend = 'F12', $activo = 'S', $moroso = 'N', $lim = 5000, $saldo = 0, $alias = null) => [
            'CLIENTE' => $cod, 'NOMBRE' => $nombre, 'ALIAS' => $alias ?? $nombre, 'DIRECCION' => 'DETALLE: BANPRO 2C AL LAGO',
            'VENDEDOR' => $vend, 'ACTIVO' => $activo, 'MOROSO' => $moroso, 'LIMITE_CREDITO' => $lim, 'SALDO' => $saldo,
        ];

        DB::table('cliente')->insert([
            $f('03164', 'DISTRIBUIDORA CRUZ AZUL, S.A. - RUC J0310000016259', lim: 60000000, saldo: 25892624.52),
            $f('00004', 'FARMACIA LA UNION - RUR 2902805650000J', moroso: 'S', lim: 2500, saldo: 3000, alias: 'FARMACIA LA UNION (León)'),
            $f('00017', 'FARMACIA OCCIDENTAL- RUC 2810504480004F'),
            $f('00213', 'FARMACIA ADELIA RUC 001-090161-0051S'),
            $f('00278', 'FARMACIA SANTA ISABEL -RUC3611003640000W'),
            $f('00011', 'FARMACIA MI FARMACIA'),
            $f('00030', 'FARMACIA ESTRUCTURA'),
            $f('00099', 'FARMACIA CERRADA - RUC 1234567890', activo: 'N'),
            $f('00500', 'FARMACIA DE OTRO VENDEDOR', vend: 'F06'),
        ]);
    }

    private function clientes(?string $vendedor = 'F12'): SqlServerClientes
    {
        return new SqlServerClientes(
            db: DB::connection(),
            tabla: 'cliente',
            columnas: config('clientes.columns'),
            whereRaw: config('clientes.where_raw'),
            vendedor: $vendedor,
            buscarEn: config('clientes.buscar_en'),
        );
    }

    public function test_separa_nombre_y_ruc_que_la_vista_cortaba_mal(): void
    {
        $c = $this->clientes();

        $this->assertSame(['DISTRIBUIDORA CRUZ AZUL, S.A.', 'J0310000016259'], [$c->encontrar('03164')->nombre, $c->encontrar('03164')->ruc]);
        $this->assertSame(['FARMACIA LA UNION', '2902805650000J'], [$c->encontrar('00004')->nombre, $c->encontrar('00004')->ruc]);
        $this->assertSame(['FARMACIA OCCIDENTAL', '2810504480004F'], [$c->encontrar('00017')->nombre, $c->encontrar('00017')->ruc]);
        $this->assertSame(['FARMACIA ADELIA', '001-090161-0051S'], [$c->encontrar('00213')->nombre, $c->encontrar('00213')->ruc]);
        $this->assertSame(['FARMACIA SANTA ISABEL', '3611003640000W'], [$c->encontrar('00278')->nombre, $c->encontrar('00278')->ruc]);
        $this->assertSame(['FARMACIA MI FARMACIA', ''], [$c->encontrar('00011')->nombre, $c->encontrar('00011')->ruc]);
        $this->assertSame('FARMACIA ESTRUCTURA', $c->encontrar('00030')->nombre); // "RUC" dentro de una palabra no cuenta
    }

    public function test_direccion_sin_prefijo_y_disponible_calculado(): void
    {
        $c = $this->clientes()->encontrar('00004');

        $this->assertSame('BANPRO 2C AL LAGO', $c->direccion);
        $this->assertTrue($c->moroso);
        $this->assertSame(2500.0, $c->credito);
        $this->assertSame(-500.0, $c->disponible); // límite - saldo
        $this->assertSame(34107375.48, $this->clientes()->encontrar('03164')->disponible);
    }

    public function test_lee_condicion_nivel_y_monedas(): void
    {
        DB::table('cliente')->where('CLIENTE', '03164')->update(['CONDICION_PAGO' => '90', 'NIVEL_PRECIO' => 'INST.PUB. U$', 'MONEDA_NIVEL' => 'D', 'MONEDA' => 'DOL']);

        $usd = $this->clientes()->encontrar('03164');
        $this->assertSame(['90', 'INST.PUB. U$', 'D', 'D'], [$usd->condicionPago, $usd->nivelPrecio, $usd->monedaNivel, $usd->moneda]);
        $this->assertTrue($usd->creditoComparable());

        $cor = $this->clientes()->encontrar('00004');
        $this->assertSame(['180', 'INST.PUB. C$', 'L', 'L'], [$cor->condicionPago, $cor->nivelPrecio, $cor->monedaNivel, $cor->moneda]);
    }

    public function test_busca_por_nombre_ruc_y_alias_solo_activos_del_vendedor(): void
    {
        $c = $this->clientes();
        $codigos = fn ($r) => array_map(fn ($x) => $x->codigo, $r);

        $this->assertSame(['03164'], $codigos($c->buscar('cruz azul')));
        $this->assertSame(['00004'], $codigos($c->buscar('2902805650000J'))); // RUC dentro del nombre
        $this->assertSame(['00004'], $codigos($c->buscar('león'))); // alias
        $this->assertNotContains('00099', $codigos($c->buscar('farmacia'))); // inactivo
        $this->assertNotContains('00500', $codigos($c->buscar('farmacia'))); // otro vendedor
        $this->assertNull($c->encontrar('00500'));
        $this->assertSame('00500', $this->clientes(null)->encontrar('00500')->codigo); // sin filtro de vendedor
    }
}
