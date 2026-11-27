<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\Reorden;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/** Pantalla Artículos: catálogo paginado, filtros y buscador (fragmento por fetch). */
class ArticulosListadoTest extends TestCase
{
    use RefreshDatabase;

    private function catalogo(int $n = 45): void
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) {
            $filas[] = ['codigo' => sprintf('ART-%03d', $i), 'descripcion' => sprintf('Producto %03d', $i), 'unidad' => 'PZA', 'precio' => 10, 'existencia' => $i % 2 ? 5 : 0];
        }
        $this->app->instance(Catalogo::class, new DemoCatalogo($filas));
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
    }

    private function reordenConMapa(array $mapa): void
    {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andReturn($mapa);
        $db = Mockery::mock(ConnectionResolverInterface::class);
        $db->shouldReceive('connection')->andReturn($conexion);
        $reorden = new Reorden($this->app['cache.store'], $db, ['habilitado' => true, 'fecha_corte' => '2026-10-26'] + config('articulos.reorden'));
        $reorden->refrescar();
        $this->app->instance(Reorden::class, $reorden);
    }

    public function test_sin_busqueda_lista_todo_el_catalogo_paginado(): void
    {
        $this->catalogo();

        $this->get('/articulos/buscar?f=todos')->assertOk()
            ->assertSeeText('Mostrando 1–20 de 45 artículos')
            ->assertSee('PRODUCTO 001')->assertDontSee('PRODUCTO 021');

        $this->get('/articulos/buscar?f=todos&page=3')->assertSeeText('Mostrando 41–45 de 45')->assertSee('PRODUCTO 045');
        $this->get('/articulos/buscar?f=todos&per=10')->assertSeeText('Mostrando 1–10 de 45');
        $this->get('/articulos/buscar?f=todos&per=7')->assertSeeText('Mostrando 1–20 de 45'); // valor no permitido: 20
        $this->get('/articulos/buscar?f=todos&page=99')->assertSeeText('Mostrando 41–45 de 45'); // fuera de rango: última
    }

    public function test_index_renderiza_la_primera_pagina_con_los_params_de_la_url(): void
    {
        $this->catalogo();

        $this->get('/articulos')->assertOk()
            ->assertSee('data-listado', false)
            ->assertSeeText('Mostrando 1–20 de 23') // por defecto: solo con existencia
            ->assertSee('Buscar artículo o código')
            ->assertSee('data-orden="articulo"', false); // encabezado ordenable

        $this->get('/articulos?q=producto 04&per=10&f=todos')->assertSeeText('Mostrando 1–7 de 7');
    }

    public function test_filtro_con_existencia_y_fila_agotada_atenuada(): void
    {
        $this->catalogo();

        $this->get('/articulos/buscar?f=existencia&per=50')->assertSeeText('de 23 artículos')->assertDontSee('row-off');
        $this->get('/articulos/buscar?per=50')->assertSeeText('de 23 artículos'); // el defecto es "con existencia"
        $html = $this->get('/articulos/buscar?f=todos&per=50')->getContent();
        $this->assertSame(22, substr_count($html, 'class="row-off"'));
    }

    public function test_filtro_prioridad_a_usa_los_codigos_del_mapa(): void
    {
        $this->catalogo();
        $this->reordenConMapa([
            (object) ['ARTICULO' => 'ART-002', 'CATEGORIA' => 'ACA', 'MESES_INVENTARIO' => '3.00', 'FECHA_CATEGORIA' => '2026-10-26'],
            (object) ['ARTICULO' => 'ART-003', 'CATEGORIA' => 'A', 'MESES_INVENTARIO' => '1.50', 'FECHA_CATEGORIA' => '2026-10-26'],
            (object) ['ARTICULO' => 'ART-004', 'CATEGORIA' => 'BCA', 'MESES_INVENTARIO' => '9.00', 'FECHA_CATEGORIA' => '2026-10-26'],
        ]);

        $this->get('/articulos/buscar?f=prioridad')->assertSeeText('de 2 artículos')
            ->assertSee('PRODUCTO 002')->assertSee('PRODUCTO 003')->assertDontSee('PRODUCTO 004');
        $this->get('/articulos')->assertSee('Prioridad A');
    }

    public function test_sin_mapa_de_categorias_el_chip_prioridad_no_se_muestra(): void
    {
        $this->catalogo();

        $this->get('/articulos')->assertOk()->assertDontSee('Prioridad A')->assertSee('Con existencia');
        $this->get('/articulos/buscar?f=prioridad')->assertSeeText('de 23 artículos'); // sin mapa: el filtro por defecto
    }

    public function test_ordena_por_columna_sobre_todo_el_listado(): void
    {
        $filas = [];
        foreach ([['A1', 'Beta', 30, 5], ['A2', 'Alfa', 10, 8], ['A3', 'Gama', 20, 0], ['A4', 'Delta', 40, 2]] as [$c, $d, $p, $e]) {
            $filas[] = ['codigo' => $c, 'descripcion' => $d, 'unidad' => 'PZA', 'precio' => $p, 'existencia' => $e];
        }
        $this->app->instance(Catalogo::class, new DemoCatalogo($filas));
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);

        // Precio de mayor a menor.
        $this->get('/articulos/buscar?f=todos&o=precio&d=desc&per=10')
            ->assertSeeInOrder(['DELTA', 'BETA', 'GAMA', 'ALFA'])
            ->assertSee('aria-sort="descending"', false);
        $this->get('/articulos/buscar?f=todos&o=existencia')->assertSeeInOrder(['GAMA', 'DELTA', 'BETA', 'ALFA']);
        // Campo no permitido: se ignora (mismo resultado que sin orden).
        $this->assertSame(
            $this->get('/articulos/buscar?f=todos')->getContent(),
            $this->get('/articulos/buscar?f=todos&o=DROP')->getContent(),
        );
    }

    public function test_ordena_por_meses_del_reorder_con_vacios_al_final(): void
    {
        $this->catalogo(4);
        $this->reordenConMapa([
            (object) ['ARTICULO' => 'ART-001', 'CATEGORIA' => 'B', 'MESES_INVENTARIO' => '10.6', 'FECHA_CATEGORIA' => '2026-10-26'],
            (object) ['ARTICULO' => 'ART-003', 'CATEGORIA' => 'A', 'MESES_INVENTARIO' => '9.0', 'FECHA_CATEGORIA' => '2026-10-26'],
        ]);

        $this->get('/articulos/buscar?f=todos&o=meses')->assertSeeInOrder(['PRODUCTO 003', 'PRODUCTO 001', 'PRODUCTO 002']);
        $this->get('/articulos/buscar?f=todos&o=meses&d=desc')->assertSeeInOrder(['PRODUCTO 001', 'PRODUCTO 003', 'PRODUCTO 002']);
        $this->get('/articulos/buscar?f=todos&o=categoria')->assertSeeInOrder(['PRODUCTO 003', 'PRODUCTO 001', 'PRODUCTO 002']);
    }

    public function test_sin_resultados_y_error_de_catalogo_muestran_estado_vacio(): void
    {
        $this->catalogo();
        $this->get('/articulos/buscar?q=zzzz')->assertSee('class="empty"', false)->assertSee('Sin resultados');

        $falla = Mockery::mock(Catalogo::class);
        $falla->shouldReceive('listar')->andThrow(new \PDOException('timeout'));
        $this->app->instance(Catalogo::class, $falla);
        $this->get('/articulos/buscar')->assertOk()->assertSee('Sin conexión al catálogo');
    }
}
