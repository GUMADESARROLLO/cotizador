<?php

namespace Tests\Feature;

use App\Clientes\SqlServerClientes;
use App\Support\Orden;
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
            foreach (['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'VENDEDOR', 'ACTIVO', 'MOROSO', 'TELEFONO1', 'TELEFONO2', 'CONTACTO', 'FAX'] as $c) {
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

    public function test_listar_pagina_toda_la_cartera_del_vendedor_ordenada_por_nombre(): void
    {
        $c = $this->clientes();
        $codigos = fn ($r) => array_map(fn ($x) => $x->codigo, $r['items']);

        // Activos de F12: 7 (sin el inactivo ni el de otro vendedor).
        $p1 = $c->listar('', 'todos', 1, 3);
        $p3 = $c->listar('', 'todos', 3, 3);

        $this->assertSame(7, $p1['total']);
        $this->assertCount(3, $p1['items']);
        $this->assertCount(1, $p3['items']);
        $this->assertSame(['03164'], $codigos($c->listar('cruz', 'todos', 1, 10)));
        $this->assertSame([], $c->listar('', 'todos', 9, 3)['items']);
        $this->assertNotContains('00500', $codigos($c->listar('', 'todos', 1, 50)));
        $this->assertNotContains('00099', $codigos($c->listar('', 'todos', 1, 50)));
    }

    public function test_listar_filtra_morosos_y_con_saldo_y_la_busqueda_pone_el_codigo_exacto_primero(): void
    {
        $c = $this->clientes();
        $codigos = fn ($r) => array_map(fn ($x) => $x->codigo, $r['items']);

        $this->assertSame(['00004'], $codigos($c->listar('', 'morosos', 1, 10)));
        $this->assertEqualsCanonicalizing(['00004', '03164'], $codigos($c->listar('', 'saldo', 1, 10)));
        $this->assertSame(1, $c->listar('farmacia', 'morosos', 1, 10)['total']);

        DB::table('cliente')->where('CLIENTE', '00030')->update(['NOMBRE' => 'AAA 00011 PRIMERO']);
        $this->assertSame('00011', $c->listar('00011', 'todos', 1, 10)['items'][0]->codigo); // exacto antes que el nombre
    }

    public function test_listar_ordena_por_columna_en_sql(): void
    {
        $c = $this->clientes();
        $codigos = fn ($r) => array_map(fn ($x) => $x->codigo, $r['items']);

        $saldo = $codigos($c->listar('', 'todos', 1, 10, new Orden('saldo', desc: true)));
        $this->assertSame(['03164', '00004'], array_slice($saldo, 0, 2));
        // Ordenado sobre toda la cartera: la página 1 de 2 en 2 ya trae los dos de mayor saldo.
        $this->assertSame(['03164', '00004'], $codigos($c->listar('', 'todos', 1, 2, new Orden('saldo', desc: true))));
        // Disponible = límite - saldo: el moroso (2,500 - 3,000) es el menor.
        $this->assertSame('00004', $codigos($c->listar('', 'todos', 1, 10, new Orden('disponible')))[0]);
    }

    public function test_contar_da_los_tres_contadores_con_la_busqueda(): void
    {
        $c = $this->clientes();

        $this->assertSame(['todos' => 7, 'morosos' => 1, 'saldo' => 2], $c->contar(''));
        $this->assertSame(['todos' => 1, 'morosos' => 1, 'saldo' => 1], $c->contar('union'));
        $this->assertSame(['todos' => 0, 'morosos' => 0, 'saldo' => 0], $c->contar('zzzz'));
    }

    public function test_lee_telefonos(): void
    {
        DB::table('cliente')->where('CLIENTE', '00004')->update(['TELEFONO1' => '2222-1111', 'TELEFONO2' => '8888-0000', 'CONTACTO' => ' LIC. ANA PEREZ ', 'FAX' => '2222-1112']);

        $c = $this->clientes()->encontrar('00004');
        $this->assertSame(['2222-1111', '8888-0000'], [$c->telefono1, $c->telefono2]);
        $this->assertSame(['LIC. ANA PEREZ', '2222-1112'], [$c->contacto, $c->fax]); // para la cotización
        $this->assertSame('', $this->clientes()->encontrar('00011')->telefono1);
    }
}
