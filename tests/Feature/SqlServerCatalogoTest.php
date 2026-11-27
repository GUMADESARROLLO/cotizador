<?php

namespace Tests\Feature;

use App\Catalogo\SqlServerCatalogo;
use App\Support\Orden;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica el mapeo de columnas y la búsqueda usando SQLite como sustituto
 * (la sintaxis LIKE ... ESCAPE es compatible con SQL Server).
 */
class SqlServerCatalogoTest extends TestCase
{
    private SqlServerCatalogo $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('inv_art', function ($t) {
            $t->string('CVE_ART');
            $t->string('DESCR');
            $t->string('UNI_MED')->nullable();
            $t->decimal('PRECIO1', 10, 2);
            $t->decimal('EXIST', 10, 2);
            $t->boolean('ACTIVO')->default(true);
        });

        DB::table('inv_art')->insert([
            ['CVE_ART' => 'A-100', 'DESCR' => 'Tubo cobre 1/2"', 'UNI_MED' => 'TRA', 'PRECIO1' => 250, 'EXIST' => 12.5, 'ACTIVO' => true],
            ['CVE_ART' => 'A-101', 'DESCR' => 'Codo cobre 1/2" 90°', 'UNI_MED' => 'PZA', 'PRECIO1' => 18, 'EXIST' => -2, 'ACTIVO' => true],
            ['CVE_ART' => 'A-102', 'DESCR' => 'Descuento 100% cobre', 'UNI_MED' => 'PZA', 'PRECIO1' => 1, 'EXIST' => 1, 'ACTIVO' => true],
            ['CVE_ART' => 'A-999', 'DESCR' => 'Tubo cobre descontinuado', 'UNI_MED' => 'TRA', 'PRECIO1' => 1, 'EXIST' => 99, 'ACTIVO' => false],
        ]);

        $this->catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            whereRaw: 'ACTIVO = 1',
        );
    }

    public function test_busca_por_varias_palabras_y_respeta_filtro(): void
    {
        $res = $this->catalogo->buscar('cobre tubo');

        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $res));
        $this->assertSame(12.5, $res[0]->maximoPedible()); // se permiten fracciones de caja
    }

    public function test_coincidencia_exacta_de_codigo_va_primero(): void
    {
        $res = $this->catalogo->buscar('A-101');
        $this->assertSame('A-101', $res[0]->codigo);
    }

    public function test_comodines_se_escapan(): void
    {
        $res = $this->catalogo->buscar('100%');
        // "%" se busca literal: solo coincide la descripción que contiene "100%".
        $this->assertSame(['A-102'], array_map(fn ($a) => $a->codigo, $res));

        // "_" no actúa como comodín de un carácter ("co_re" no debe encontrar "cobre").
        $this->assertSame([], $this->catalogo->buscar('co_re'));
    }

    public function test_existencia_negativa_cuenta_como_cero(): void
    {
        $a = $this->catalogo->encontrar('A-101');
        $this->assertSame(0.0, $a->existencia);
        $this->assertFalse($a->disponible());
    }

    public function test_existencia_desde_tabla_de_bodegas_excluyendo_004(): void
    {
        Schema::create('exist_bod', function ($t) {
            $t->string('ARTICULO');
            $t->string('BODEGA');
            $t->decimal('CANT_DISPONIBLE', 10, 2);
        });
        DB::table('exist_bod')->insert([
            ['ARTICULO' => 'A-100', 'BODEGA' => '001', 'CANT_DISPONIBLE' => 10],
            ['ARTICULO' => 'A-100', 'BODEGA' => '002', 'CANT_DISPONIBLE' => 5.5],
            ['ARTICULO' => 'A-100', 'BODEGA' => '004', 'CANT_DISPONIBLE' => 900],
            ['ARTICULO' => 'A-102', 'BODEGA' => '004', 'CANT_DISPONIBLE' => 50],
        ]);

        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            whereRaw: 'a.ACTIVO = 1',
            stock: ['table' => 'exist_bod', 'articulo' => 'ARTICULO', 'cantidad' => 'CANT_DISPONIBLE', 'bodega' => 'BODEGA', 'excluir' => ['004']],
        );

        $res = $catalogo->encontrarVarios(['A-100', 'A-101', 'A-102']);

        $this->assertSame(15.5, $res['A-100']->existencia);
        $this->assertSame(15.5, $res['A-100']->maximoPedible());
        $this->assertSame(0.0, $res['A-101']->existencia); // sin filas de existencia
        $this->assertSame(0.0, $res['A-102']->existencia); // solo en la 004

        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $catalogo->buscar('tubo')));
    }

    public function test_precio_desde_nivel_vigente_y_oculta_sin_precio(): void
    {
        Schema::create('precios', function ($t) {
            $t->string('ARTICULO');
            $t->string('NIVEL_PRECIO');
            $t->decimal('PRECIO', 10, 2);
            $t->string('VIGENCIA');
        });
        DB::table('precios')->insert([
            ['ARTICULO' => 'A-100', 'NIVEL_PRECIO' => 'INST.PUB. C$', 'PRECIO' => 1, 'VIGENCIA' => 'S'],
            ['ARTICULO' => 'A-100', 'NIVEL_PRECIO' => 'FARMACIA', 'PRECIO' => 250, 'VIGENCIA' => 'S'],
            ['ARTICULO' => 'A-101', 'NIVEL_PRECIO' => 'INST.PUB. C$', 'PRECIO' => 15.5, 'VIGENCIA' => 'S'],
            ['ARTICULO' => 'A-101', 'NIVEL_PRECIO' => 'INST.PUB. C$', 'PRECIO' => 99, 'VIGENCIA' => 'N'], // vencido
            // A-102 no tiene precio en el nivel: no se muestra
        ]);

        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            whereRaw: 'a.ACTIVO = 1 and p.precio > 0',
            precio: ['table' => 'precios', 'articulo' => 'ARTICULO', 'precio' => 'PRECIO', 'nivel_columna' => 'NIVEL_PRECIO', 'nivel' => 'INST.PUB. C$', 'where_raw' => "VIGENCIA = 'S'"],
        );

        $res = $catalogo->encontrarVarios(['A-100', 'A-101', 'A-102']);

        $this->assertSame(1.0, $res['A-100']->precio); // tal cual, aunque sea C$1
        $this->assertSame(15.5, $res['A-101']->precio); // ignora el precio no vigente
        $this->assertArrayNotHasKey('A-102', $res);
        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $catalogo->buscar('tubo')));
    }

    public function test_sin_precio_en_el_nivel_tambien_se_muestra_con_precio_cero(): void
    {
        // El precio se pone a mano en el pedido: no se ocultan los artículos sin precio.
        Schema::create('precios', function ($t) {
            $t->string('ARTICULO');
            $t->string('NIVEL_PRECIO');
            $t->decimal('PRECIO', 10, 2);
        });
        DB::table('precios')->insert(['ARTICULO' => 'A-100', 'NIVEL_PRECIO' => 'FARMACIA', 'PRECIO' => 250]);

        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            whereRaw: 'a.ACTIVO = 1',
            precio: ['table' => 'precios', 'articulo' => 'ARTICULO', 'precio' => 'PRECIO', 'nivel_columna' => 'NIVEL_PRECIO', 'nivel' => 'INST.PUB. U$'],
        );

        $res = $catalogo->buscar('tubo');
        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $res));
        $this->assertSame(0.0, $res[0]->precio);
    }

    public function test_lee_factor_y_unidad_de_empaque(): void
    {
        Schema::table('inv_art', function ($t) {
            $t->decimal('FACTOR', 10, 2)->nullable();
            $t->string('UNI_EMP')->nullable();
        });
        DB::table('inv_art')->where('CVE_ART', 'A-100')->update(['FACTOR' => 50, 'UNI_EMP' => 'VIA']);

        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST', 'factor' => 'FACTOR', 'unidad_empaque' => 'UNI_EMP'],
        );

        $a = $catalogo->encontrar('A-100');
        $this->assertSame(50.0, $a->factor);
        $this->assertSame('VIA', $a->unidadEmpaque);
        $this->assertTrue($a->tieneEmpaque());
    }

    public function test_codigos_numericos_se_envian_como_texto(): void
    {
        // Las claves del carrito como "13401062" llegan como int desde array_keys().
        DB::table('inv_art')->insert(['CVE_ART' => '13401062', 'DESCR' => 'Numérico', 'UNI_MED' => 'CJA', 'PRECIO1' => 5, 'EXIST' => 3, 'ACTIVO' => true]);
        $claves = array_keys(['13401062' => 1, 'A-100' => 1]);
        $this->assertIsInt($claves[0]);

        DB::enableQueryLog();
        $res = $this->catalogo->encontrarVarios($claves);
        $bindings = DB::getQueryLog()[0]['bindings'];

        $this->assertEqualsCanonicalizing(['13401062', 'A-100'], array_map('strval', array_keys($res)));
        $this->assertContains('13401062', $bindings, 'el código debe enlazarse como string');
        $this->assertNotContains(13401062, $bindings, true);
    }

    public function test_existencia_solo_de_las_bodegas_incluidas(): void
    {
        Schema::create('exist_bod', function ($t) {
            $t->string('ARTICULO');
            $t->string('BODEGA');
            $t->decimal('CANT_DISPONIBLE', 10, 2);
        });
        DB::table('exist_bod')->insert([
            ['ARTICULO' => 'A-100', 'BODEGA' => '006', 'CANT_DISPONIBLE' => 7],
            ['ARTICULO' => 'A-100', 'BODEGA' => '002', 'CANT_DISPONIBLE' => 500],
            ['ARTICULO' => 'A-102', 'BODEGA' => '002', 'CANT_DISPONIBLE' => 50],
        ]);

        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            stock: ['table' => 'exist_bod', 'articulo' => 'ARTICULO', 'cantidad' => 'CANT_DISPONIBLE', 'bodega' => 'BODEGA', 'incluir' => ['006'], 'excluir' => ['004']],
        );

        $res = $catalogo->encontrarVarios(['A-100', 'A-102']);
        $this->assertSame(7.0, $res['A-100']->existencia); // ignora los 500 de la 002
        $this->assertSame(0.0, $res['A-102']->existencia); // no tiene en 006: agotado
    }

    public function test_encontrar_varios(): void
    {
        $res = $this->catalogo->encontrarVarios(['A-100', 'A-102', 'NO']);
        $this->assertEqualsCanonicalizing(['A-100', 'A-102'], array_keys($res));
    }

    public function test_listar_pagina_todo_el_catalogo_con_total(): void
    {
        $p1 = $this->catalogo->listar('', 'todos', 1, 2);
        $p2 = $this->catalogo->listar('', 'todos', 2, 2);

        // Activos ordenados por descripción: Codo, Descuento, Tubo (A-999 inactivo no cuenta).
        $this->assertSame(3, $p1['total']);
        $this->assertSame(['A-101', 'A-102'], array_map(fn ($a) => $a->codigo, $p1['items']));
        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $p2['items']));
        $this->assertSame([], $this->catalogo->listar('', 'todos', 9, 2)['items']);
    }

    public function test_listar_con_busqueda_y_filtros(): void
    {
        $this->assertSame(1, $this->catalogo->listar('tubo', 'todos', 1, 20)['total']);
        $this->assertSame(0, $this->catalogo->listar('nada', 'todos', 1, 20)['total']);

        // Con existencia: A-101 tiene -2 (cuenta como sin existencia).
        $con = $this->catalogo->listar('', 'existencia', 1, 20);
        $this->assertSame(2, $con['total']);
        $this->assertEqualsCanonicalizing(['A-100', 'A-102'], array_map(fn ($a) => $a->codigo, $con['items']));

        // Prioridad: solo los códigos indicados (y sin códigos, nada).
        $prio = $this->catalogo->listar('', 'prioridad', 1, 20, ['A-102', 'A-999']);
        $this->assertSame(['A-102'], array_map(fn ($a) => $a->codigo, $prio['items']));
        $this->assertSame(0, $this->catalogo->listar('', 'prioridad', 1, 20, [])['total']);
    }

    public function test_listar_busqueda_pone_primero_el_codigo_exacto(): void
    {
        $res = $this->catalogo->listar('A-102', 'todos', 1, 20);
        $this->assertSame('A-102', $res['items'][0]->codigo);
    }

    public function test_listar_existencia_filtra_con_tabla_de_bodegas(): void
    {
        Schema::create('exist_bod', function ($t) {
            $t->string('ARTICULO');
            $t->string('BODEGA');
            $t->decimal('CANT_DISPONIBLE', 12, 2);
        });
        DB::table('exist_bod')->insert([
            ['ARTICULO' => 'A-100', 'BODEGA' => '006', 'CANT_DISPONIBLE' => 7],
            ['ARTICULO' => 'A-102', 'BODEGA' => '002', 'CANT_DISPONIBLE' => 500],
        ]);
        $catalogo = new SqlServerCatalogo(
            db: DB::connection(),
            tabla: 'inv_art',
            columnas: ['codigo' => 'CVE_ART', 'descripcion' => 'DESCR', 'unidad' => 'UNI_MED', 'precio' => 'PRECIO1', 'existencia' => 'EXIST'],
            whereRaw: 'ACTIVO = 1',
            stock: ['table' => 'exist_bod', 'articulo' => 'ARTICULO', 'cantidad' => 'CANT_DISPONIBLE', 'bodega' => 'BODEGA', 'incluir' => ['006']],
        );

        $res = $catalogo->listar('', 'existencia', 1, 20);
        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $res['items']));
        $this->assertSame(1, $res['total']);
        $this->assertSame(3, $catalogo->listar('', 'todos', 1, 20)['total']);
    }

    public function test_listar_ordena_por_columna_y_por_valores_externos(): void
    {
        $codigos = fn (array $r) => array_map(fn ($a) => $a->codigo, $r['items']);

        $porPrecio = $this->catalogo->listar('', 'todos', 1, 10, [], new Orden('precio', desc: true));
        $this->assertSame(['A-100', 'A-101', 'A-102'], $codigos($porPrecio));

        // Meses (Reorder Point): numérico (10.6 > 9.0, no como texto) y sin valor al final.
        $valores = ['A-101' => 10.6, 'A-102' => 9.0];
        $asc = $this->catalogo->listar('', 'todos', 1, 10, [], new Orden('meses'), $valores);
        $this->assertSame(['A-102', 'A-101', 'A-100'], $codigos($asc));
        $desc = $this->catalogo->listar('', 'todos', 1, 10, [], new Orden('meses', desc: true), $valores);
        $this->assertSame(['A-101', 'A-102', 'A-100'], $codigos($desc));
    }
}
