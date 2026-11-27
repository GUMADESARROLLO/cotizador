<?php

namespace Tests\Feature;

use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use App\Cotizaciones\Concentracion;
use App\Cotizaciones\CotizacionExcel;
use App\Cotizaciones\FichaCotizacion;
use App\Models\Pedido;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Cotización en Excel sobre la plantilla oficial: se valida el .xlsx generado leyendo su XML
 * (celdas, fórmulas, combinaciones, formas conservadas y desplazadas).
 */
class CotizacionExcelTest extends TestCase
{
    use RefreshDatabase;

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @var list<string> textos compartidos del último archivo abierto */
    private array $compartidos = [];

    private function linea(string $desc, float $cant, float $precio, ?string $vence = '2028-04-30'): array
    {
        return ['cantidad' => $cant, 'descripcion' => $desc, 'concentracion' => Concentracion::de($desc),
            'precio' => $precio, 'vence' => $vence ? Carbon::parse($vence) : null];
    }

    private function datos(array $lineas, array $extra = []): array
    {
        return $extra + [
            'licitacion' => 'CA-158-10-2026', 'cliente' => 'Distribuidora Cruz Azul', 'atencion' => 'Lic. Oscar Estrada',
            'cargo' => '', 'telefono' => '2249-9191', 'fax' => '2249-0899', 'fecha' => Carbon::parse('2026-10-26'),
            'moneda' => 'L', 'condicion' => 'Crédito 60 días', 'lineas' => $lineas,
        ];
    }

    /** @return array{0: DOMXPath, 1: DOMXPath, 2: ZipArchive} hoja, dibujo, zip */
    private function abrir(string $archivo): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $xp = function (string $parte) use ($zip) {
            $doc = new DOMDocument;
            $doc->loadXML($zip->getFromName($parte));
            $x = new DOMXPath($doc);
            $x->registerNamespace('m', self::NS);
            $x->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');

            return $x;
        };

        $this->compartidos = [];
        foreach ($xp('xl/sharedStrings.xml')->query('//m:si') as $si) {
            $this->compartidos[] = $si->textContent;
        }

        return [$xp('xl/worksheets/sheet1.xml'), $xp('xl/drawings/drawing1.xml'), $zip];
    }

    private function valor(DOMXPath $x, string $ref): ?string
    {
        $c = $x->query("//m:c[@r='{$ref}']")->item(0);
        if (! $c) {
            return null;
        }
        $is = $x->query('m:is', $c)->item(0);
        $v = $x->query('m:v', $c)->item(0)?->textContent;

        return $is ? $is->textContent : ($c->getAttribute('t') === 's' ? $this->compartidos[(int) $v] : $v);
    }

    private function generar(array $datos): string
    {
        return app(CotizacionExcel::class)->generar($datos);
    }

    public function test_una_linea_llena_la_plantilla_sin_mover_nada(): void
    {
        $f = $this->generar($this->datos([$this->linea('ANASTROZOL 1 MG TAB RECUBIERTA 28/CAJA', 23604, 4.97, '2028-10-31')]));
        [$h, $dib, $zip] = $this->abrir($f);

        $this->assertSame('COTIZACION CA-158-10-2026', $this->valor($h, 'H3'));
        $this->assertSame(' Ref: CA-158-10-2026', $this->valor($h, 'G7')); // etiqueta en negrita conservada
        $this->assertSame('Cliente: Distribuidora Cruz Azul', $this->valor($h, 'A4'));
        $this->assertSame('Atención: Lic. Oscar Estrada', $this->valor($h, 'A5'));
        $this->assertSame('Cargo: ', $this->valor($h, 'A6'));
        $this->assertSame('Teléfono: 2249-9191 Fax: 2249-0899', $this->valor($h, 'A7'));
        $this->assertSame(' Fecha: 26 de Octubre 2026', $this->valor($h, 'G4'));
        $this->assertNull($this->valor($h, 'G8')); // línea verde en blanco

        $this->assertSame('1', $this->valor($h, 'A11'));
        $this->assertSame('23604', $this->valor($h, 'B11'));
        $this->assertSame('ANASTROZOL 1 MG TAB RECUBIERTA 28/CAJA', $this->valor($h, 'C11'));
        $this->assertSame('1 mg', $this->valor($h, 'F11'));
        $this->assertSame('B11*G11', $h->query("//m:c[@r='H11']/m:f")->item(0)->textContent);
        $this->assertSame('INMEDIATO', $this->valor($h, 'I11'));
        $this->assertSame((string) CotizacionExcel::serial(Carbon::parse('2028-10-31')), $this->valor($h, 'J11'));
        $this->assertSame('SUM(H11:H12)', $h->query("//m:c[@r='I13']/m:f")->item(0)->textContent);
        $this->assertEqualsWithDelta(117311.88, (float) $this->valor($h, 'I13'), 0.001);
        $this->assertSame('   *    Términos de pago: Crédito 60 días', $this->valor($h, 'A18'));
        $this->assertSame('Lic. Cesia Moreira', $this->valor($h, 'B24'));

        // Formas de la plantilla intactas: dirección, 3 recuadros redondeados y logo.
        $this->assertSame(4, $dib->query('//xdr:sp')->length);
        $this->assertSame(1, $dib->query('//xdr:pic')->length);
        $this->assertSame('16', $dib->query("//xdr:twoCellAnchor[.//*[@name='AutoShape 14']]/xdr:from/xdr:row")->item(0)->textContent);
        // Sin calcChain (apuntaría a celdas movidas) y con recálculo al abrir.
        $this->assertFalse($zip->locateName('xl/calcChain.xml'));
        $this->assertStringContainsString('fullCalcOnLoad="1"', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($f);
    }

    public function test_varias_lineas_desplazan_totales_observaciones_y_firmas(): void
    {
        $lineas = [];
        for ($i = 1; $i <= 4; $i++) {
            $lineas[] = $this->linea("PRODUCTO {$i} 500 MG TABLETA", 10 * $i, 2.5);
        }
        $lineas[] = $this->linea('SIN VENCIMIENTO 1 G', 1, 1.4108, null);
        $f = $this->generar($this->datos($lineas));
        [$h, $dib, $zip] = $this->abrir($f);
        $delta = 4;

        foreach (range(11, 15) as $i => $r) {
            $this->assertSame((string) ($i + 1), $this->valor($h, "A{$r}"));
            $this->assertSame("B{$r}*G{$r}", $h->query("//m:c[@r='H{$r}']/m:f")->item(0)->textContent);
            $this->assertSame(1, $h->query("//m:mergeCell[@ref='C{$r}:E{$r}']")->length);
        }
        $this->assertNull($this->valor($h, 'J15')); // sin lote: sin fecha
        $this->assertSame('1.4108', $this->valor($h, 'G15')); // precio por unidad con sus decimales
        $this->assertSame('SUM(H11:H'.(12 + $delta).')', $h->query("//m:c[@r='I".(13 + $delta)."']/m:f")->item(0)->textContent);
        $this->assertSame('Observaciones:', $this->valor($h, 'A'.(16 + $delta)));
        $this->assertSame('Lic. Cesia Moreira', $this->valor($h, 'B'.(24 + $delta)));
        $this->assertSame(1, $h->query("//m:mergeCell[@ref='B".(24 + $delta).':D'.(24 + $delta)."']")->length);
        $this->assertSame('A1:N'.(26 + $delta), $h->query('//m:dimension')->item(0)->getAttribute('ref'));
        // El recuadro de Observaciones baja con las filas; los de arriba no se mueven.
        $this->assertSame((string) (16 + $delta), $dib->query("//xdr:twoCellAnchor[.//*[@name='AutoShape 14']]/xdr:from/xdr:row")->item(0)->textContent);
        $this->assertSame('3', $dib->query("//xdr:twoCellAnchor[.//*[@name='INVB1']]/xdr:from/xdr:row")->item(0)->textContent);
        // Hasta 8 artículos: una sola hoja.
        $this->assertSame('1', $h->query('//m:pageSetup')->item(0)->getAttribute('fitToHeight'));
        $zip->close();
        unlink($f);
    }

    public function test_en_dolares_cambia_los_encabezados_y_el_formato_del_total(): void
    {
        $f = $this->generar($this->datos([$this->linea('ANASTROZOL 1 MG', 1, 10)], ['moneda' => 'D']));
        [$h, , $zip] = $this->abrir($f);

        $this->assertSame('Precio Unitario US$ ', $this->valor($h, 'G10'));
        $this->assertSame('Total US$', $this->valor($h, 'H10'));
        $this->assertSame('Total US$', $this->valor($h, 'H13'));
        $this->assertStringContainsString('&quot;US$&quot;', $zip->getFromName('xl/styles.xml'));
        $zip->close();
        unlink($f);
    }

    public function test_muchas_lineas_usan_varias_hojas_sin_partir_las_firmas(): void
    {
        $lineas = array_map(fn ($i) => $this->linea("PRODUCTO {$i} CON UNA DESCRIPCION LARGA DE VARIOS RENGLONES 500 MG/100 ML (LABORATORIO)", 1, 1), range(1, 14));
        $f = $this->generar($this->datos($lineas));
        [$h, , $zip] = $this->abrir($f);

        $this->assertSame('0', $h->query('//m:pageSetup')->item(0)->getAttribute('fitToHeight'));
        $brk = $h->query('//m:rowBreaks/m:brk')->item(0);
        if ($brk) { // si hay salto, es justo antes de "Observaciones"
            $this->assertSame((string) (15 + 13), $brk->getAttribute('id'));
        }
        $zip->close();
        unlink($f);
    }

    public function test_datos_desde_el_pedido_en_la_unidad_capturada(): void
    {
        $p = Pedido::create(['cliente' => 'FARMACIA LOPEZ', 'cliente_codigo' => '00005', 'contacto' => '', 'licitacion' => 'L-1',
            'condicion_pago' => '01', 'moneda' => 'L', 'lineas' => 2, 'piezas' => 2, 'total' => 0]);
        $p->items()->create(['codigo' => 'A1', 'descripcion' => 'Amoxicilina 500mg Capsula 100/Caja', 'unidad' => 'CJA', 'precio' => 141.08,
            'cantidad' => 100, 'modo' => 'unidad', 'unidades' => 10000, 'factor' => 100, 'unidad_empaque' => 'CAP', 'existencia' => 0, 'subtotal' => 14108, 'lote_vence' => '2029-05-31']);
        $p->items()->create(['codigo' => 'A2', 'descripcion' => 'Glucosa 50%', 'unidad' => 'CJA', 'precio' => 87.89,
            'cantidad' => 3, 'modo' => 'caja', 'existencia' => 0, 'subtotal' => 263.67]);

        $cliente = (new DemoClientes)->encontrar('00005');
        $d = CotizacionExcel::datos($p->load('items'), $cliente);

        $this->assertSame('L-1', $d['licitacion']);
        // Manda la condición de Softland al día (180); la del pedido ("01" = contado) es respaldo.
        $this->assertSame('Crédito 180 días', $d['condicion']);
        $this->assertSame('Contado', CotizacionExcel::datos($p, null)['condicion']);
        $this->assertSame(10000.0, $d['lineas'][0]['cantidad']); // capturado en unidades
        $this->assertEqualsWithDelta(1.4108, $d['lineas'][0]['precio'], 0.00001);
        $this->assertSame('AMOXICILINA 500MG CAPSULA 100/CAJA', $d['lineas'][0]['descripcion']);
        $this->assertSame('500 mg', $d['lineas'][0]['concentracion']);
        $this->assertSame(3.0, $d['lineas'][1]['cantidad']); // en cajas
        $this->assertSame('2270-00005', $d['telefono']);
        $this->assertSame('Crédito 180 días', CotizacionExcel::condicion('180'));
    }

    public function test_sin_condicion_de_pago_no_queda_el_60_de_la_plantilla(): void
    {
        $f = $this->generar($this->datos([$this->linea('ANASTROZOL 1 MG', 1, 10)], ['condicion' => '']));
        [$h, , $zip] = $this->abrir($f);

        $this->assertSame('   *    Términos de pago:', $this->valor($h, 'A18'));
        $zip->close();
        unlink($f);
    }

    public function test_linea_verde_con_el_responsable(): void
    {
        $f = $this->generar($this->datos([$this->linea('ANASTROZOL 1 MG', 1, 10)], ['responsable' => 'Lic. Fabiola Guerreri Molina']));
        [$h, , $zip] = $this->abrir($f);

        $this->assertSame('Lic. Fabiola Guerreri Molina', $this->valor($h, 'G8'));
        $zip->close();
        unlink($f);
    }

    public function test_la_ficha_del_cliente_se_recuerda_entre_pedidos(): void
    {
        $this->app->instance(Clientes::class, new DemoClientes);
        $nuevo = function () {
            $p = Pedido::create(['cliente' => 'FARMACIA LOPEZ', 'cliente_codigo' => '00005', 'moneda' => 'L', 'lineas' => 1, 'piezas' => 1, 'total' => 10]);
            $p->items()->create(['codigo' => 'A1', 'descripcion' => 'Anastrozol 1 mg', 'unidad' => 'CJA', 'precio' => 10, 'cantidad' => 1, 'existencia' => 0, 'subtotal' => 10]);

            return $p;
        };

        // Primera vez: se prellena con Softland (teléfono del cliente) y el nombre del pedido.
        $p1 = $nuevo();
        $this->get(route('pedidos.show', $p1))->assertOk()
            ->assertSee('name="nombre" value="FARMACIA LOPEZ"', false)
            ->assertSee('name="telefono" value="2270-00005"', false);

        $this->post(route('pedidos.cotizacion', $p1), [
            'licitacion' => 'CA-173-11-2026', 'nombre' => 'Farmacia López', 'atencion' => 'Lic. Oscar Estrada',
            'cargo' => 'Gerente General', 'telefono' => '2249-9191', 'fax' => '2249-0899', 'responsable' => 'Lic. Fabiola Guerreri Molina',
        ])->assertOk();

        // Otro pedido del mismo cliente: ya viene todo lleno (lo guardado manda sobre Softland).
        $p2 = $nuevo();
        $this->get(route('pedidos.show', $p2))
            ->assertSee('name="nombre" value="Farmacia López"', false)
            ->assertSee('name="atencion" value="Lic. Oscar Estrada"', false)
            ->assertSee('name="cargo" value="Gerente General"', false)
            ->assertSee('name="telefono" value="2249-9191"', false)
            ->assertSee('name="fax" value="2249-0899"', false)
            // Licitación y responsable son de cada cotización: vacíos, con el responsable anterior como sugerencia.
            ->assertSee('name="licitacion" value=""', false)
            ->assertSee('name="responsable" value=""', false)
            ->assertSee('<option value="Lic. Fabiola Guerreri Molina">', false);

        // El pedido 1 conserva su responsable; el 2 lleva otro sin pisar al anterior.
        $this->assertSame('Lic. Fabiola Guerreri Molina', $p1->fresh()->responsable);
        $this->post(route('pedidos.cotizacion', $p2), ['licitacion' => 'CA-180-11-2026', 'responsable' => 'Lic. Fátima Putoy'] + [
            'nombre' => 'Farmacia López', 'atencion' => 'Lic. Oscar Estrada', 'cargo' => 'Gerente General', 'telefono' => '2249-9191', 'fax' => '2249-0899',
        ])->assertOk();
        $this->assertSame(['Lic. Fabiola Guerreri Molina', 'Lic. Fátima Putoy'], [$p1->fresh()->responsable, $p2->fresh()->responsable]);

        $d = CotizacionExcel::datos($p2->fresh()->load('items'), (new DemoClientes)->encontrar('00005'));
        $this->assertSame(['Farmacia López', 'Lic. Oscar Estrada', 'Gerente General', '2249-9191', '2249-0899', 'Lic. Fátima Putoy'],
            [$d['cliente'], $d['atencion'], $d['cargo'], $d['telefono'], $d['fax'], $d['responsable']]);
        // Sugerencias: las dos, la más reciente primero.
        $this->assertSame(['Lic. Fátima Putoy', 'Lic. Fabiola Guerreri Molina'], FichaCotizacion::responsables($nuevo()));
    }

    public function test_el_detalle_descarga_el_excel_y_guarda_la_licitacion(): void
    {
        $this->app->instance(Clientes::class, new DemoClientes);
        $p = Pedido::create(['cliente' => 'FARMACIA LOPEZ', 'cliente_codigo' => '00005', 'moneda' => 'L', 'lineas' => 1, 'piezas' => 1, 'total' => 10]);
        $p->items()->create(['codigo' => 'A1', 'descripcion' => 'Anastrozol 1 mg', 'unidad' => 'CJA', 'precio' => 10, 'cantidad' => 1, 'existencia' => 0, 'subtotal' => 10]);

        $this->get(route('pedidos.show', $p))->assertOk()
            ->assertSee('Cotización Excel')->assertDontSee('window.print()', false)
            ->assertSee(route('pedidos.cotizacion', $p), false);

        $res = $this->post(route('pedidos.cotizacion', $p), ['licitacion' => 'CA-200/11-2026'])->assertOk();
        $this->assertStringContainsString('COTIZACION CA-200-11-2026.xlsx', $res->headers->get('content-disposition'));
        $this->assertSame('CA-200/11-2026', $p->fresh()->licitacion);
        $this->assertStringStartsWith('PK', file_get_contents($res->getFile()->getPathname()));
    }
}
