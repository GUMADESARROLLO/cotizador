<?php

namespace Tests\Feature;

use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla Cliente: los últimos 3 clientes con pedido, para elegirlos sin buscar.
 */
class ClientesRecientesTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $codigo, string $nombre, string $fecha): void
    {
        $p = Pedido::create(['cliente_codigo' => $codigo, 'cliente' => $nombre, 'lineas' => 1, 'piezas' => 1, 'total' => 1]);
        $p->forceFill(['created_at' => $fecha])->save();
    }

    public function test_sin_pedidos_no_se_muestra(): void
    {
        $this->get('/clientes')->assertOk()->assertDontSee('Recientes');
    }

    public function test_muestra_los_ultimos_3_sin_repetir_y_permite_elegirlos(): void
    {
        $this->pedido('00010', 'CLIENTE VIEJO', '2026-11-20 08:00:00');
        $this->pedido('00003', 'CLIENTE TRES', '2026-11-24 09:00:00');
        $this->pedido('00005', 'CLIENTE CINCO', '2026-11-25 10:00:00');
        $this->pedido('00003', 'CLIENTE TRES', '2026-11-26 11:00:00'); // repetido: cuenta una vez
        $this->pedido('00007', 'CLIENTE SIETE', '2026-11-26 12:00:00');

        $this->get('/clientes')->assertOk()
            ->assertSee('Recientes')
            ->assertSeeInOrder(['CLIENTE SIETE', 'CLIENTE TRES', 'CLIENTE CINCO'])
            ->assertSee('Último pedido 26/11/2026 11:00')
            ->assertDontSee('CLIENTE VIEJO');

        // Un toque lo elige (con sus datos al día de Softland) y lleva a artículos.
        $this->post('/clientes/seleccionar', ['codigo' => '00003'])->assertRedirect(route('articulos.index'));
    }
}
