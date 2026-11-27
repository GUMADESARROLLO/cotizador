<?php

namespace Tests\Unit;

use App\Cotizaciones\Concentracion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcentracionTest extends TestCase
{
    public static function casos(): array
    {
        return [
            ['ANASTROZOL 1 MG TAB RECUBIERTA 28/CAJA', '1 mg'],
            ['amoxicilina 500mg Capsula 100/Caja (Reyoung)', '500 mg'],
            ['Beclometasona 250 mcg/Dosis Suspension para Inhalacion', '250 mcg/dosis'],
            ['Ciprofloxacina 200 mg/100 ml Solucion para Infusion IV', '200 mg/100 ml'],
            ['Clindamicina 150 mg/ml Solucion Inyectable 6ml/Ampolla', '150 mg/ml'],
            ['AC. GLUTAMICO/VIT B1 (300 MG/20 MG) CAPSULA 10/BLISTER', '300 mg/20 mg'],
            ['Glucosa en Agua 50% Solución Inyectable 50ml/Frasco', '50%'],
            ['DEXAMETASONA 8 MG/2 ML SOLUCION INYECTABLE', '8 mg/2 ml'],
            ['HEPARINA 5,000 UI/ML', '5,000 UI/ml'],
            ['Ceftriaxona 1Gr Polvo para Sol. iny. i.M/i.V Vial 50/Caja (Reyoung)', '1 GR'],
            ['ifosfamida 1 Gr PPSi Vial 1/Caja (Naprod)', '1 GR'],
            ['Cefazolina 1g Polvo para Sol. iny. Vial 50/Caja (Reyoung)', '1 g'],
            ['Pomada Divina  7gr Lata/ 24/ Caja (Divina)', '7 GR'],
            ['Dipirona Sodica 1g/2ml Solucion inyectable 100 ampollas/Caja', '1 g/2 ml'],
            ['Bencilpenicilina Sodica 1,000,000 ui Polvo para Sol. iny. Vial unidad', '1,000,000 UI'],
            ['Nistatina/Tinidazol (100,000 ui/150 mg) ovulo 14/Caja (Ramos)', '100,000 UI/150 mg'],
            ['CALNOR (Amlodipina + Olmesartan) 10mgx40mg Tabletas Recubiertas 28/Caja', '10 mg x 40 mg'],
            ['GENERADOR MO-99/TC-99M mCi -  TERABAC 131l x 200 mCi', '200 mCi'],
            ['IODO 1131 Presentacion 200 mCi', '200 mCi'],
            ['Trimexam (Trimetoprim/Sulfametoxazol) 40mg x 200mg/5ml Suspension oral 100 ml/Frasco', '40 mg x 200 mg/5 ml'],
            ['Hidroxido de aluminio+ Hidroxido de Magnesio 150mgx150mg/5ml Suspension oral 200 ml', '150 mg x 150 mg/5 ml'],
            ['Clanic Bid (amoxicilina/acido Clavulanico) 875mg x 125mg Tabletas Recubiertas 10/Caja', '875 mg x 125 mg'],
            ['Jeringa de Plastico Descartable 3cc/21Gx1.5" 100/Caja (Qiaopai)', ''], // 21G = calibre de aguja
            ['SESTAMIBI 0.5 mg RADIOFARMA', '0.5 mg'],
            ['Closantel 5% Sol. iny. 50ml/Frasco 1/Caja', '5%'],
            // El volumen o la cantidad del envase no es concentración.
            ['expectorante Compuesto Jarabe 120 ml/Frasco 1/Caja (Lancasco)', ''],
            ['Korin (antigripal) Solucion oral Gotas 30ml/Frasco 1/Caja (Karnel)', ''],
            ['DTPA KIT 5 UNIDADES', ''],
            ['ABRE BOCA TRANSPARENTE GRANDE UNIDAD', ''],
        ];
    }

    #[DataProvider('casos')]
    public function test_extrae_la_concentracion_de_la_descripcion(string $descripcion, string $esperado): void
    {
        $this->assertSame($esperado, Concentracion::de($descripcion));
    }
}
