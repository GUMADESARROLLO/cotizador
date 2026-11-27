<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\Reorden;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReordenTest extends TestCase
{
    use RefreshDatabase;

    private int $consultas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $fila = fn ($codigo) => ['codigo' => $codigo, 'descripcion' => "Artículo {$codigo}", 'unidad' => 'CJA', 'precio' => 10, 'existencia' => 50];
        $this->app->instance(Catalogo::class, new DemoCatalogo([$fila('10118022'), $fila('10115012'), $fila('99999999')]));
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);
    }

    /** Servicio habilitado con un SQL Server simulado (filas o excepción). */
    private function reorden(array|RuntimeException $resultado, array $config = []): Reorden
    {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andReturnUsing(function () use ($resultado) {
            $this->consultas++;
            if ($resultado instanceof RuntimeException) {
                throw $resultado;
            }

            return $resultado;
        });
        $db = Mockery::mock(ConnectionResolverInterface::class);
        $db->shouldReceive('connection')->andReturn($conexion);

        $reorden = new Reorden(
            cache: $this->app['cache.store'],
            db: $db,
            config: $config + ['habilitado' => true, 'fecha_corte' => '2026-10-26'] + config('articulos.reorden'),
        );
        $this->app->instance(Reorden::class, $reorden);

        return $reorden;
    }

    private function filas(): array
    {
        return [
            (object) ['ARTICULO' => '10118022 ', 'CATEGORIA' => 'CCA', 'MESES_INVENTARIO' => '14.70', 'FECHA_CATEGORIA' => '2026-10-26'],
            (object) ['ARTICULO' => '10115012', 'CATEGORIA' => 'A', 'MESES_INVENTARIO' => null, 'FECHA_CATEGORIA' => '2026-10-26'],
        ];
    }

    private function buscar()
    {
        return $this->get('/articulos/buscar?q='.rawurlencode('Artículo'))->assertOk();
    }

    public function test_muestra_categoria_y_meses_desde_la_cache_sin_consultar(): void
    {
        $this->reorden($this->filas())->refrescar();
        $this->consultas = 0;

        $this->buscar()
            ->assertSeeTextInOrder(['10115012', 'Categoría A', '— meses'])
            ->assertSeeTextInOrder(['10118022', 'Categoría CCA', '14.7', 'meses'])
            ->assertSeeInOrder(['99999999', 'Cat. —'])
            ->assertSee('foto del 26/10/2026');

        $this->assertSame(0, $this->consultas, 'La búsqueda no debe ejecutar la función de SQL Server');
    }

    public function test_sin_cache_no_muestra_nada_y_la_carga_en_segundo_plano(): void
    {
        // El refresco corre DESPUÉS de enviar la respuesta (defer, al terminar la petición).
        $this->reorden($this->filas());

        $this->buscar()->assertDontSee('Cat. —')->assertDontSee('CCA');
        $this->assertSame(1, $this->consultas);
        $this->assertTrue(Cache::has('reorden_mapa'));

        $this->app->forgetScopedInstances();
        $this->reorden($this->filas());
        $this->buscar()->assertSee('CCA');
        $this->assertSame(1, $this->consultas, 'Con el mapa vigente no se vuelve a consultar');
    }

    public function test_si_sql_server_falla_la_busqueda_sigue_y_no_reintenta_enseguida(): void
    {
        $this->reorden(new RuntimeException('timeout'));

        $this->buscar()->assertSee('ARTÍCULO 10118022')->assertDontSee('Cat. —');
        $this->buscar()->assertSee('ARTÍCULO 10118022');

        $this->assertSame(1, $this->consultas);
    }

    public function test_otra_fecha_de_corte_no_reutiliza_el_mapa(): void
    {
        $this->reorden($this->filas())->refrescar();

        $otra = $this->reorden($this->filas(), ['fecha_corte' => '2026-11-11']);
        $this->assertFalse($otra->cargado());
        $this->assertTrue(Cache::has('reorden_mapa'));
    }

    public function test_con_catalogo_demo_queda_deshabilitado(): void
    {
        $this->assertFalse(app(Reorden::class)->habilitado());
        $this->buscar()->assertDontSee('Cat. —');
    }
}
