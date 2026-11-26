<?php

namespace Tests\Feature;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\Imagenes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagenesTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tbl_product', function ($t) {
            $t->string('product_sku');
            $t->string('product_image')->nullable();
        });
        DB::table('tbl_product')->insert([
            ['product_sku' => 'A1', 'product_image' => 'foto-a1.png'],
            ['product_sku' => 'b2 ', 'product_image' => 'foto-b2.png'], // espacio y minúscula
            ['product_sku' => 'C3', 'product_image' => ''],
            ['product_sku' => 'D4', 'product_image' => 'no-esta-en-s3.png'],
        ]);

        config(['filesystems.disks.s3' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'bucket' => 'b']]);
        Storage::fake('s3');
        Storage::disk('s3')->put('product/foto-a1.png', $this->png(1200, 800));
        Storage::disk('s3')->put('product/foto-b2.png', $this->png(600, 900));

        $this->carpeta = storage_path('framework/testing/thumbs-'.uniqid());
        $this->app->instance(Imagenes::class, $this->servicio());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    private function servicio(): Imagenes
    {
        return new Imagenes(
            cache: $this->app['cache.store'],
            db: $this->app['db'],
            storage: $this->app['filesystem'],
            config: ['connection' => 'sqlite'] + config('articulos.imagenes'),
            carpeta: $this->carpeta,
            urlBase: '/thumbs',
        );
    }

    private function png(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, intdiv($w, 2), $h, 0x2B3B9E);
        imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), 0x29B6E8);
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_sin_miniatura_apunta_a_la_ruta_que_la_genera(): void
    {
        $urls = app(Imagenes::class)->urls(['A1', 'B2', 'C3', 'NO-EXISTE']);

        $this->assertSame([
            'A1' => route('imagenes.miniatura', 'A1'),
            'B2' => route('imagenes.miniatura', 'B2'),
        ], $urls);
    }

    public function test_genera_webp_pequeno_una_vez_y_luego_es_estatico(): void
    {
        $res = $this->get('/img/A1')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $ruta = $res->baseResponse->getFile()->getPathname();

        [$w, $h, $tipo] = getimagesize($ruta);
        $this->assertSame(IMAGETYPE_WEBP, $tipo);
        $this->assertSame([160, 107], [$w, $h]); // conserva proporción 3:2
        $this->assertLessThan(15 * 1024, filesize($ruta));

        // Ya generada: la búsqueda apunta al archivo estático, sin PHP ni S3.
        $url = app(Imagenes::class)->urls(['A1'])['A1'];
        $this->assertStringStartsWith('/thumbs/', $url);
        $this->assertStringEndsWith('.webp', $url);
    }

    public function test_si_la_foto_cambia_se_genera_otra_miniatura(): void
    {
        $this->get('/img/A1')->assertOk();
        $antes = app(Imagenes::class)->urls(['A1'])['A1'];

        DB::table('tbl_product')->where('product_sku', 'A1')->update(['product_image' => 'foto-a1-v2.png']);
        Storage::disk('s3')->put('product/foto-a1-v2.png', $this->png(300, 300));
        cache()->forget('img_mapa');

        $this->assertNotSame($antes, app(Imagenes::class)->urls(['A1'])['A1']);
    }

    public function test_sin_foto_o_ausente_en_s3_responde_404(): void
    {
        $this->get('/img/C3')->assertNotFound();
        $this->get('/img/D4')->assertNotFound();
        $this->get('/img/NADA')->assertNotFound();
    }

    public function test_sin_credenciales_no_hay_imagenes(): void
    {
        config(['filesystems.disks.s3.key' => null]);
        $this->assertSame([], app(Imagenes::class)->urls(['A1']));
    }

    public function test_si_falla_la_base_de_fotos_no_rompe_la_busqueda(): void
    {
        Schema::drop('tbl_product');
        $this->assertSame([], app(Imagenes::class)->urls(['A1']));
    }

    public function test_resultados_muestran_miniatura_solo_si_hay_foto(): void
    {
        $this->app->instance(Catalogo::class, new DemoCatalogo([
            ['codigo' => 'A1', 'descripcion' => 'Con foto', 'unidad' => 'CJA', 'precio' => 10, 'existencia' => 5],
            ['codigo' => 'Z9', 'descripcion' => 'Sin foto', 'unidad' => 'CJA', 'precio' => 10, 'existencia' => 5],
        ]));

        $this->post('/clientes/seleccionar', ['codigo' => '00003']); // cliente demo
        $html = $this->get('/articulos/buscar?q=foto')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<img'));
        $this->assertStringContainsString('src="'.route('imagenes.miniatura', 'A1').'"', $html);
        $this->assertStringContainsString('width="80" height="80"', $html);
    }
}
