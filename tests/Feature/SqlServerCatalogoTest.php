<?php

namespace Tests\Feature;

use App\Catalogo\SqlServerCatalogo;
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
        $this->assertSame(12.0, $res[0]->maximoPedible());
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
        $this->assertSame(15.0, $res['A-100']->maximoPedible());
        $this->assertSame(0.0, $res['A-101']->existencia); // sin filas de existencia
        $this->assertSame(0.0, $res['A-102']->existencia); // solo en la 004

        $this->assertSame(['A-100'], array_map(fn ($a) => $a->codigo, $catalogo->buscar('tubo')));
    }

    public function test_encontrar_varios(): void
    {
        $res = $this->catalogo->encontrarVarios(['A-100', 'A-102', 'NO']);
        $this->assertEqualsCanonicalizing(['A-100', 'A-102'], array_keys($res));
    }
}
