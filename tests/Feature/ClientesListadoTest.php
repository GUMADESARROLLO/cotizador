<?php

namespace Tests\Feature;

use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla Clientes: cartera paginada con segmentado, contadores y elección por fila.
 */
class ClientesListadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_toda_la_cartera_del_vendedor_con_contadores_y_pie(): void
    {
        $this->get('/clientes')->assertOk()
            ->assertSee('FARMACIA LA PROVIDENCIA')
            ->assertDontSee('OTRO VENDEDOR')
            ->assertSee('data-todos="6"', false)
            ->assertSee('data-morosos="1"', false)
            ->assertSeeText('Mostrando 1–6 de 6 clientes')
            ->assertSee('name="codigo" value="00003"', false); // botón real por fila
    }

    public function test_segmento_morosos_y_paginacion_por_params(): void
    {
        $this->get('/clientes/buscar?f=morosos')
            ->assertSee('FARMACIA LA UNION')->assertDontSee('FARMACIA LOPEZ')
            ->assertSeeText('de 1 clientes');

        $this->get('/clientes/buscar?per=10&page=2')->assertSeeText('Mostrando 1–6 de 6'); // la página se ajusta a la última
        $this->get('/clientes/buscar?per=abc&f=raro')->assertOk()->assertSee('FARMACIA LOPEZ'); // valores inválidos = defecto
    }

    public function test_paginador_y_pagina_inicial_desde_la_url(): void
    {
        $filas = [];
        for ($i = 1; $i <= 12; $i++) {
            $filas[] = ['codigo' => sprintf('%05d', $i), 'nombre' => sprintf('CLIENTE %02d', $i), 'vendedor' => 'F12'];
        }
        $this->app->instance(Clientes::class, new DemoClientes($filas));

        $this->get('/clientes?per=10&page=2')->assertOk()
            ->assertSeeText('Mostrando 11–12 de 12 clientes')
            ->assertSee('CLIENTE 12')->assertDontSee('CLIENTE 01');
        $this->get('/clientes/buscar?per=10')->assertSee('data-page="2"', false);
    }

    public function test_sin_resultados_y_error_de_sql_server_se_muestran_en_la_card(): void
    {
        $this->get('/clientes/buscar?q=zzzz')->assertSee('Sin resultados')->assertSee('zzzz');

        $this->app->instance(Clientes::class, new class extends DemoClientes
        {
            public function contar(string $q): array
            {
                throw new \PDOException('caído');
            }
        });
        $this->get('/clientes/buscar')->assertOk()->assertSee('Sin conexión a clientes');
    }

    public function test_cliente_actual_se_marca_y_hay_atajo_para_continuar(): void
    {
        $this->post('/clientes/seleccionar', ['codigo' => '00003']);

        $this->get('/clientes')->assertSee('Continuar con FARMACIA LA PROVIDENCIA')->assertSee('Actual');
    }

    public function test_ordena_por_columna_sobre_toda_la_cartera(): void
    {
        // Disponible de mayor a menor (empates por nombre); campo no permitido se ignora.
        $this->get('/clientes/buscar?o=disponible&d=desc')->assertOk()
            ->assertSeeInOrder(['FARMACIA LOPEZ', 'CLINICA PRECIO', 'ORGANISMO EN DOLARES', 'HOSPITAL CENTRAL'])
            ->assertSee('aria-sort="descending"', false);
        $this->get('/clientes/buscar?o=disponible')->assertSeeInOrder(['HOSPITAL CENTRAL', 'FARMACIA LOPEZ']);
        $this->get('/clientes/buscar?o=cliente&d=desc')->assertSeeInOrder(['ORGANISMO EN DOLARES', 'HOSPITAL CENTRAL', 'CLINICA PRECIO']);
        $this->get('/clientes/buscar?o=nombre;DROP')->assertOk()->assertDontSee('aria-sort="ascending"', false);
    }
}
