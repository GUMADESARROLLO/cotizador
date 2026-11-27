<?php

namespace App\Cotizaciones;

use App\Clientes\Cliente;
use App\Models\Pedido;
use App\Pedidos\Linea;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Cotización en Excel sobre la plantilla oficial (resources/plantillas/cotizacion.xlsx,
 * la COTIZACION CA-158-10-2026.xls convertida a .xlsx por Excel sin cambios).
 *
 * Se edita el XML del .xlsx directamente, sin pasar por una librería que reescriba
 * el libro: así se conservan TAL CUAL el logo, los recuadros redondeados (formas que
 * PhpSpreadsheet descarta), la dirección, estilos, bordes y la configuración de impresión.
 *
 * Mapa de la plantilla (hoja Cot1):
 *   H3  "COTIZACION <licitación>"      G7  " Ref: <licitación>"
 *   A4  "Cliente: …"   A5 "Atención: …"   A6 "Cargo: …"   A7 "Teléfono: … Fax: …"
 *   G4  " Fecha: 26 de Octubre 2026"   G8 (línea verde: se deja en blanco)
 *   Fila 11 = artículo (se clona una por línea), fila 12 = fila vacía de cierre.
 *   I13 total (SUM), A18 términos de pago, B24 firma. Todo lo que está debajo de la
 *   fila 11 (incluido el recuadro de Observaciones) se desplaza al agregar filas.
 */
final class CotizacionExcel
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_XDR = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const FILA_ITEM = 11;

    private const ALTO_ITEM = 36.75; // alto original de la fila 11 (puntos)

    // Hasta este número de artículos la cotización va en una sola hoja (como la plantilla).
    private const UNA_HOJA = 8;

    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private DOMDocument $hoja;

    private DOMXPath $xp;

    /** styles.xml, solo si hace falta un estilo nuevo (se guarda al final). */
    private ?DOMDocument $estilos = null;

    /** @var list<DOMElement> sharedStrings <si> */
    private array $textos = [];

    public function __construct(
        private string $plantilla,
        private string $firma,
        private string $tiempoEntrega = 'INMEDIATO',
    ) {}

    /**
     * Datos de la cotización a partir de un pedido guardado.
     *
     * @return array{licitacion:string, cliente:string, atencion:string, cargo:string, telefono:string, fax:string,
     *               fecha:Carbon, moneda:string, condicion:string, lineas:list<array{cantidad:float, descripcion:string, concentracion:string, precio:float, vence:?Carbon}>}
     */
    public static function datos(Pedido $pedido, ?Cliente $cliente = null): array
    {
        $lineas = [];
        foreach ($pedido->items as $item) {
            $l = $item->comoLinea();
            // En la unidad en que se capturó: unidades internas (TBL, VIA…) o cajas.
            $enUnidades = ($l['modo'] ?? 'caja') === 'unidad' && Linea::tieneEmpaque($l);
            $lineas[] = [
                'cantidad' => (float) ($enUnidades ? $l['unidades'] : $item->cantidad),
                'descripcion' => mb_strtoupper(trim((string) $item->descripcion)), // como la plantilla
                'concentracion' => Concentracion::de((string) $item->descripcion),
                'precio' => round((float) ($enUnidades ? Linea::precioUnidad($l) : $item->precio), 4),
                'vence' => $item->lote_vence ? Carbon::parse($item->lote_vence) : null,
            ];
        }

        return [
            'licitacion' => trim((string) $pedido->licitacion),
            'cliente' => (string) $pedido->cliente,
            'atencion' => trim((string) $pedido->contacto) !== '' ? trim((string) $pedido->contacto) : (string) $cliente?->contacto,
            'cargo' => '', // a pedido del usuario: se llena a mano por ahora
            // Un teléfono (como la plantilla): con dos más el fax, la línea se sale del recuadro.
            'telefono' => trim((string) $cliente?->telefono1) ?: trim((string) $cliente?->telefono2),
            'fax' => (string) $cliente?->fax,
            'fecha' => Carbon::parse($pedido->fecha_pedido ?? $pedido->created_at),
            'moneda' => $pedido->moneda === 'D' ? 'D' : 'L',
            'condicion' => self::condicion((string) $pedido->condicion_pago),
            'lineas' => $lineas,
        ];
    }

    /** Condición de pago de Softland ("60", "180", "01" = contado) en palabras. */
    public static function condicion(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $dias = (int) $codigo;

        return $dias <= 1 ? 'Contado' : "Crédito {$dias} días";
    }

    /**
     * Genera el .xlsx y devuelve la ruta del archivo temporal.
     *
     * @param  array<string, mixed>  $d  ver datos()
     */
    public function generar(array $d): string
    {
        if (! is_file($this->plantilla)) {
            throw new RuntimeException("No existe la plantilla de cotización: {$this->plantilla}");
        }

        $destino = tempnam(sys_get_temp_dir(), 'cot');
        copy($this->plantilla, $destino);

        $zip = new ZipArchive;
        if ($zip->open($destino) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla de cotización.');
        }

        $this->estilos = null;
        $this->hoja = $this->xml($zip, 'xl/worksheets/sheet1.xml');
        $this->xp = new DOMXPath($this->hoja);
        $this->xp->registerNamespace('m', self::NS);
        $compartidos = $this->xml($zip, 'xl/sharedStrings.xml');
        $this->textos = iterator_to_array($compartidos->getElementsByTagNameNS(self::NS, 'si'));
        $dibujo = $this->xml($zip, 'xl/drawings/drawing1.xml');

        $lineas = array_values($d['lineas']);
        $n = max(1, count($lineas));
        $delta = $n - 1;
        $altos = array_map(fn ($l) => self::alto($l['descripcion']), $lineas ?: [['descripcion' => '']]);
        // Cuánto bajan (en EMU) las formas que están debajo de los artículos.
        $bajarEmu = (int) round((array_sum($altos) - self::ALTO_ITEM) * 12700);

        $this->insertarFilas($delta);
        $this->moverFormas($dibujo, $delta, $bajarEmu);
        $this->ajustarHoja($delta, count($lineas));

        // ---- Encabezado ----
        $lic = trim((string) $d['licitacion']);
        $this->texto('H3', trim("COTIZACION {$lic}"));
        $this->texto('G7', $lic !== '' ? ": {$lic}" : ':', rico: true);
        $this->texto('A4', 'Cliente: '.$d['cliente']);
        $this->texto('A5', ' '.$d['atencion'], rico: true);
        $this->texto('A6', ' '.$d['cargo'], rico: true);
        $tel = ' '.$d['telefono'].(trim((string) $d['fax']) !== '' ? ' Fax: '.$d['fax'] : '');
        $this->texto('A7', $tel, rico: true);
        $f = $d['fecha'];
        $this->texto('G4', sprintf(' Fecha: %d de %s %d', $f->day, self::MESES[$f->month - 1], $f->year));
        $this->vaciar('G8');

        // ---- Moneda ----
        $simbolo = $d['moneda'] === 'D' ? 'US$' : 'C$';
        $this->texto('G10', "Precio Unitario {$simbolo} ");
        $this->texto('H10', "Total {$simbolo}");
        $filaTotal = 13 + $delta;
        $this->texto('H'.$filaTotal, "Total {$simbolo}");
        // Total: reducir hasta ajustar (un monto grande no sale como ####) y, en dólares, "US$".
        $this->celda('I'.$filaTotal)->setAttribute('s', (string) $this->clonarEstilo($zip, (int) $this->celda('I'.$filaTotal)->getAttribute('s'),
            $d['moneda'] === 'D' ? [300, '"US$"\ #,##0.00;[Red]"US$"\ #,##0.00'] : null, encoger: true));

        // ---- Artículos ----
        $total = 0.0;
        $estiloPrecio4 = null; // precio por unidad con más de 2 decimales (1.4108): se muestran todos
        foreach ($lineas as $i => $l) {
            $r = self::FILA_ITEM + $i;
            $this->fila($r)->setAttribute('ht', (string) $altos[$i]);
            $this->fila($r)->setAttribute('customHeight', '1');
            $this->numero("A{$r}", $i + 1);
            $this->numero("B{$r}", $l['cantidad']);
            $this->texto("C{$r}", $l['descripcion']);
            $this->texto("F{$r}", $l['concentracion']);
            $this->numero("G{$r}", $l['precio']);
            if (round($l['precio'], 2) != $l['precio']) {
                $estiloPrecio4 ??= $this->clonarEstilo($zip, (int) $this->celda("G{$r}")->getAttribute('s'),
                    [301, '_ * #,##0.00##_ ;_ * \-#,##0.00##_ ;_ * "-"??_ ;_ @_ ']);
                $this->celda("G{$r}")->setAttribute('s', (string) $estiloPrecio4);
            }
            $sub = round($l['cantidad'] * $l['precio'], 2);
            $total += $sub;
            $this->formula("H{$r}", "B{$r}*G{$r}", $sub);
            $this->texto("I{$r}", $this->tiempoEntrega);
            $l['vence'] ? $this->numero("J{$r}", self::serial($l['vence'])) : $this->vaciar("J{$r}");
        }
        if ($lineas === []) {
            foreach (['A', 'B', 'C', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $this->vaciar($col.self::FILA_ITEM);
            }
        }
        $this->formula('I'.$filaTotal, 'SUM(H11:H'.(12 + $delta).')', round($total, 2));

        // ---- Observaciones y firma ----
        if ($d['condicion'] !== '') {
            $this->texto('A'.(18 + $delta), '   *    Términos de pago: '.$d['condicion']);
        }
        $this->texto('B'.(24 + $delta), $this->firma);

        $this->paginar($delta, count($lineas));

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->hoja->saveXML());
        $zip->addFromString('xl/drawings/drawing1.xml', $dibujo->saveXML());
        if ($this->estilos) {
            $zip->addFromString('xl/styles.xml', $this->estilos->saveXML());
        }
        $this->recalcularAlAbrir($zip);
        $zip->close();

        return $destino;
    }

    /** Inserta $delta filas de artículo (clones de la 11) y baja todo lo que estaba debajo. */
    private function insertarFilas(int $delta): void
    {
        if ($delta <= 0) {
            return;
        }

        $sheetData = $this->xp->query('//m:sheetData')->item(0);
        foreach (array_reverse(iterator_to_array($this->xp->query('//m:sheetData/m:row'))) as $row) {
            $r = (int) $row->getAttribute('r');
            if ($r > self::FILA_ITEM) {
                $this->renumerar($row, $r + $delta);
            }
        }

        $modelo = $this->fila(self::FILA_ITEM);
        $despues = $modelo->nextSibling;
        for ($i = 1; $i <= $delta; $i++) {
            $copia = $modelo->cloneNode(true);
            $this->renumerar($copia, self::FILA_ITEM + $i);
            $sheetData->insertBefore($copia, $despues);
        }

        // Combinaciones: las de abajo bajan; cada fila nueva lleva su C:E combinada.
        $merges = $this->xp->query('//m:mergeCells')->item(0);
        foreach ($this->xp->query('//m:mergeCells/m:mergeCell') as $m) {
            $m->setAttribute('ref', preg_replace_callback('/([A-Z]+)(\d+)/', fn ($x) => $x[1].((int) $x[2] > self::FILA_ITEM ? (int) $x[2] + $delta : $x[2]), $m->getAttribute('ref')));
        }
        for ($i = 1; $i <= $delta; $i++) {
            $r = self::FILA_ITEM + $i;
            $nuevo = $this->hoja->createElementNS(self::NS, 'mergeCell');
            $nuevo->setAttribute('ref', "C{$r}:E{$r}");
            $merges->appendChild($nuevo);
        }
        $merges->setAttribute('count', (string) $merges->childNodes->length);
    }

    private function renumerar(DOMElement $row, int $r): void
    {
        $row->setAttribute('r', (string) $r);
        foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $c) {
            $c->setAttribute('r', preg_replace('/\d+$/', (string) $r, $c->getAttribute('r')));
        }
    }

    /** Baja las formas ancladas debajo de los artículos (recuadro de Observaciones). */
    private function moverFormas(DOMDocument $dibujo, int $delta, int $bajarEmu): void
    {
        $xp = new DOMXPath($dibujo);
        $xp->registerNamespace('xdr', self::NS_XDR);
        $xp->registerNamespace('a', self::NS_A);

        foreach ($xp->query('//xdr:twoCellAnchor|//xdr:oneCellAnchor') as $ancla) {
            $desde = $xp->query('xdr:from/xdr:row', $ancla)->item(0);
            if ((int) $desde->textContent < self::FILA_ITEM) { // 0-based: filas 12 en adelante
                continue;
            }
            foreach ($xp->query('xdr:from/xdr:row|xdr:to/xdr:row', $ancla) as $fila) {
                $fila->textContent = (string) ((int) $fila->textContent + $delta);
            }
            foreach ($xp->query('.//a:xfrm/a:off', $ancla) as $off) {
                $off->setAttribute('y', (string) ((int) $off->getAttribute('y') + $bajarEmu));
            }
        }
    }

    private function ajustarHoja(int $delta, int $articulos): void
    {
        $this->xp->query('//m:dimension')->item(0)?->setAttribute('ref', 'A1:N'.(26 + $delta));

        // Abre arriba (la plantilla quedó guardada desplazada a la fila 4).
        $vista = $this->xp->query('//m:sheetView')->item(0);
        $vista?->removeAttribute('topLeftCell');
        foreach ($this->xp->query('//m:sheetView/m:selection') as $sel) {
            $sel->setAttribute('activeCell', 'A1');
            $sel->setAttribute('sqref', 'A1');
        }

        // Pocos artículos: una sola hoja, como la plantilla (Excel reduce la escala).
        // Muchos: ancho de una hoja y alto libre (ver paginar()).
        $pagina = $this->xp->query('//m:pageSetup')->item(0);
        $pagina?->setAttribute('fitToWidth', '1');
        $pagina?->setAttribute('fitToHeight', $articulos <= self::UNA_HOJA ? '1' : '0');
    }

    /**
     * Con varias hojas: si Observaciones + firmas (filas 16..26 de la plantilla) quedarían
     * partidas entre dos hojas, se pone un salto justo antes para que pasen juntas a la
     * siguiente. Se simula la paginación con los altos reales de las filas.
     */
    private function paginar(int $delta, int $articulos): void
    {
        if ($articulos <= self::UNA_HOJA) {
            return;
        }

        // Carta vertical, márgenes de la plantilla (0.75" arriba y abajo) y escala ≈81 %
        // (la que resulta de ajustar al ancho), con un poco de margen de seguridad.
        $capacidad = (11 - 0.75 - 0.75) * 72 / 0.81 - 20;
        $inicio = 16 + $delta;
        $fin = 26 + $delta;

        $y = 0.0;
        for ($r = 1; $r <= $fin; $r++) {
            $fila = $this->xp->query("//m:sheetData/m:row[@r='{$r}']")->item(0);
            $alto = $fila && $fila->getAttribute('ht') !== '' ? (float) $fila->getAttribute('ht') : 13.5;
            if ($y + $alto > $capacidad) {
                if ($r > $inicio) { // la hoja se corta dentro del bloque de observaciones/firmas
                    $this->saltoAntesDe($inicio);
                }
                if ($r >= $inicio) {
                    return;
                }
                $y = 0.0;
            }
            $y += $alto;
        }
    }

    private function saltoAntesDe(int $fila): void
    {
        $saltos = $this->hoja->createElementNS(self::NS, 'rowBreaks');
        $saltos->setAttribute('count', '1');
        $saltos->setAttribute('manualBreakCount', '1');
        $brk = $saltos->appendChild($this->hoja->createElementNS(self::NS, 'brk'));
        $brk->setAttribute('id', (string) ($fila - 1)); // la hoja nueva empieza en $fila
        $brk->setAttribute('max', '16383');
        $brk->setAttribute('man', '1');
        // Orden del esquema: …pageSetup, headerFooter, rowBreaks, colBreaks, …, drawing.
        $dibujo = $this->xp->query('//m:drawing')->item(0);
        $dibujo->parentNode->insertBefore($saltos, $dibujo);
    }

    // ---------------------------------------------------------------- celdas

    private function fila(int $r): DOMElement
    {
        $row = $this->xp->query("//m:sheetData/m:row[@r='{$r}']")->item(0);
        if (! $row) {
            throw new RuntimeException("La plantilla no tiene la fila {$r}.");
        }

        return $row;
    }

    private function celda(string $ref): DOMElement
    {
        $c = $this->xp->query("//m:sheetData/m:row/m:c[@r='{$ref}']")->item(0);
        if ($c) {
            return $c;
        }

        // No existe: se crea en su fila, en orden de columna.
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
        $row = $this->fila((int) $m[2]);
        $c = $this->hoja->createElementNS(self::NS, 'c');
        $c->setAttribute('r', $ref);
        foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $otra) {
            preg_match('/^([A-Z]+)/', $otra->getAttribute('r'), $o);
            if (strlen($o[1]) > strlen($m[1]) || (strlen($o[1]) === strlen($m[1]) && strcmp($o[1], $m[1]) > 0)) {
                return $row->insertBefore($c, $otra);
            }
        }

        return $row->appendChild($c);
    }

    private function limpiar(DOMElement $c): void
    {
        while ($c->firstChild) {
            $c->removeChild($c->firstChild);
        }
        $c->removeAttribute('t');
    }

    /**
     * Texto en la celda conservando su estilo. Con $rico, la celda tenía texto con
     * formato mixto ("Atención:" en negrita + valor normal): se conserva la etiqueta y
     * se reemplaza solo el último tramo.
     */
    private function texto(string $ref, string $valor, bool $rico = false): void
    {
        $c = $this->celda($ref);
        $original = $c->getAttribute('t') === 's'
            ? ($this->textos[(int) $c->getElementsByTagNameNS(self::NS, 'v')->item(0)?->textContent] ?? null)
            : null;

        $this->limpiar($c);
        $c->setAttribute('t', 'inlineStr');
        $is = $c->appendChild($this->hoja->createElementNS(self::NS, 'is'));

        $tramos = $original ? $original->getElementsByTagNameNS(self::NS, 'r') : null;
        if ($rico && $tramos && $tramos->length > 1) {
            foreach ($tramos as $i => $tramo) {
                $copia = $this->hoja->importNode($tramo, true);
                if ($i === $tramos->length - 1) {
                    $t = $copia->getElementsByTagNameNS(self::NS, 't')->item(0);
                    $t->textContent = $valor;
                    $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                }
                $is->appendChild($copia);
            }

            return;
        }

        $t = $is->appendChild($this->hoja->createElementNS(self::NS, 't'));
        $t->textContent = $valor;
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    }

    private function numero(string $ref, float|int $valor): void
    {
        $c = $this->celda($ref);
        $this->limpiar($c);
        $c->appendChild($this->hoja->createElementNS(self::NS, 'v', self::num($valor)));
    }

    /** Fórmula con su valor ya calculado (se ve bien aunque el visor no recalcule). */
    private function formula(string $ref, string $formula, float $valor): void
    {
        $c = $this->celda($ref);
        $this->limpiar($c);
        $c->appendChild($this->hoja->createElementNS(self::NS, 'f', $formula));
        $c->appendChild($this->hoja->createElementNS(self::NS, 'v', self::num($valor)));
    }

    private function vaciar(string $ref): void
    {
        $this->limpiar($this->celda($ref));
    }

    // ---------------------------------------------------------------- libro

    private function xml(ZipArchive $zip, string $parte): DOMDocument
    {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = true;
        if (! $doc->loadXML((string) $zip->getFromName($parte))) {
            throw new RuntimeException("Plantilla dañada: {$parte}");
        }

        return $doc;
    }

    /**
     * La cadena de cálculo (calcChain) de la plantilla apunta a celdas que se movieron:
     * se quita y se pide recalcular al abrir (si no, Excel ofrece "reparar" el archivo).
     */
    private function recalcularAlAbrir(ZipArchive $zip): void
    {
        $zip->deleteName('xl/calcChain.xml');
        foreach (['[Content_Types].xml' => '/<Override[^>]+calcChain\.xml"[^>]*\/>/', 'xl/_rels/workbook.xml.rels' => '/<Relationship[^>]+calcChain\.xml"[^>]*\/>/'] as $parte => $patron) {
            $zip->addFromString($parte, preg_replace($patron, '', (string) $zip->getFromName($parte)));
        }

        $libro = $this->xml($zip, 'xl/workbook.xml');
        $calc = $libro->getElementsByTagNameNS(self::NS, 'calcPr')->item(0);
        $calc?->setAttribute('fullCalcOnLoad', '1');

        $zip->addFromString('xl/workbook.xml', $libro->saveXML());
    }

    /**
     * Copia de un estilo de celda con otro formato de número ([id, código]: "US$", 4 decimales…)
     * y/o con "reducir hasta ajustar".
     *
     * @param  array{0:int, 1:string}|null  $formato
     */
    private function clonarEstilo(ZipArchive $zip, int $estilo, ?array $formato, bool $encoger = false): int
    {
        $doc = $this->estilos ??= $this->xml($zip, 'xl/styles.xml');
        $xfs = $doc->getElementsByTagNameNS(self::NS, 'cellXfs')->item(0);
        $copia = $xfs->getElementsByTagNameNS(self::NS, 'xf')->item($estilo)->cloneNode(true);

        if ($formato) {
            [$id, $codigo] = $formato;
            $numFmts = $doc->getElementsByTagNameNS(self::NS, 'numFmts')->item(0);
            $fmt = $doc->createElementNS(self::NS, 'numFmt');
            $fmt->setAttribute('numFmtId', (string) $id);
            $fmt->setAttribute('formatCode', $codigo);
            $numFmts->appendChild($fmt);
            $numFmts->setAttribute('count', (string) $numFmts->getElementsByTagNameNS(self::NS, 'numFmt')->length);
            $copia->setAttribute('numFmtId', (string) $id);
            $copia->setAttribute('applyNumberFormat', '1');
        }
        if ($encoger) {
            $alineacion = $copia->getElementsByTagNameNS(self::NS, 'alignment')->item(0)
                ?? $copia->appendChild($doc->createElementNS(self::NS, 'alignment'));
            $alineacion->setAttribute('shrinkToFit', '1');
            $alineacion->removeAttribute('wrapText'); // Excel ignora "reducir" si la celda ajusta texto
            $copia->setAttribute('applyAlignment', '1');
        }

        $xfs->appendChild($copia);
        $total = $xfs->getElementsByTagNameNS(self::NS, 'xf')->length;
        $xfs->setAttribute('count', (string) $total);

        return $total - 1;
    }

    // ---------------------------------------------------------------- utilidades

    /**
     * Alto de la fila del artículo: la descripción va en C:E combinadas (≈29-31 caracteres
     * de Courier New 10 por renglón) y Excel no ajusta solo el alto de celdas combinadas.
     */
    public static function alto(string $descripcion): float
    {
        $renglones = 0;
        $actual = 0;
        foreach (preg_split('/\s+/', trim($descripcion)) ?: [] as $palabra) {
            $largo = mb_strlen($palabra);
            if ($actual === 0 || $actual + 1 + $largo > 29) {
                $renglones += (int) max(1, ceil($largo / 29));
                $actual = $largo % 29 ?: 29;
            } else {
                $actual += 1 + $largo;
            }
        }

        return max(self::ALTO_ITEM, $renglones * 13.5 + 10);
    }

    /** Fecha → número de serie de Excel (días desde 1899-12-30). */
    public static function serial(Carbon $fecha): int
    {
        return (int) Carbon::create(1899, 12, 30)->startOfDay()->diffInDays($fecha->copy()->startOfDay());
    }

    private static function num(float|int $v): string
    {
        return is_int($v) ? (string) $v : rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }
}
