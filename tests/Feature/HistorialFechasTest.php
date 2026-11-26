<?php

namespace Tests\Feature;

use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Historial: filtro Desde / Hasta por fecha de creación del pedido.
 */
class HistorialFechasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');

        foreach (['2026-11-20 08:00:00' => 'CLIENTE VIEJO', '2026-11-24 17:30:00' => 'CLIENTE MARTES', '2026-11-26 09:00:00' => 'CLIENTE HOY'] as $fecha => $cliente) {
            $p = Pedido::create(['cliente' => $cliente, 'lineas' => 1, 'piezas' => 1, 'total' => 10]);
            $p->forceFill(['created_at' => $fecha])->save();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sin_filtro_muestra_todos_y_los_selectores(): void
    {
        $this->get('/pedidos')->assertOk()
            ->assertSee(['CLIENTE VIEJO', 'CLIENTE MARTES', 'CLIENTE HOY'])
            ->assertSee('data-filtro-fecha="desde"', false)
            ->assertSee('data-filtro-fecha="hasta"', false)
            ->assertSee('data-filtro-sheet', false)
            ->assertSee('Cualquiera');
    }

    public function test_filtra_el_rango_con_ambos_dias_incluidos(): void
    {
        $this->get('/pedidos?desde=2026-11-24&hasta=2026-11-26')->assertOk()
            ->assertSee(['CLIENTE MARTES', 'CLIENTE HOY'])
            ->assertDontSee('CLIENTE VIEJO')
            ->assertSee('Mar 24 nov 2026')
            ->assertSee('2 pedido(s) en el rango');

        $this->get('/pedidos?hasta=2026-11-24')->assertSee(['CLIENTE VIEJO', 'CLIENTE MARTES'])->assertDontSee('CLIENTE HOY');
        $this->get('/pedidos?desde=2026-11-25')->assertSee('CLIENTE HOY')->assertDontSee('CLIENTE MARTES');
    }

    public function test_rango_al_reves_se_ordena_y_se_combina_con_la_busqueda(): void
    {
        $this->get('/pedidos?desde=2026-11-26&hasta=2026-11-20&q=MARTES')
            ->assertSee('CLIENTE MARTES')
            ->assertDontSee(['CLIENTE HOY', 'CLIENTE VIEJO']);
    }

    public function test_fecha_invalida_no_rompe(): void
    {
        $this->get('/pedidos?desde=26/11/2026')->assertRedirect();
    }
}
