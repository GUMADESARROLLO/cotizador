<?php

namespace App\Catalogo;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fotos de productos (mismo origen que SAC): el archivo sale de tbl_product
 * y el original está en S3 en "product/<archivo>" (PNG de ~200–400 KB).
 *
 * Para no afectar el rendimiento no se envían los originales al navegador:
 * se genera una miniatura WebP pequeña UNA sola vez por producto en
 * public/thumbs/, que luego el servidor web entrega como archivo estático.
 * El nombre depende del archivo original, así que si la foto cambia se
 * genera otra y la caché del navegador nunca muestra una foto vieja.
 *
 * Cualquier falla (MySQL, S3, imagen corrupta) equivale a "sin foto":
 * nunca rompe la búsqueda.
 */
class Imagenes
{
    public function __construct(
        private Cache $cache,
        private ConnectionResolverInterface $db,
        private FilesystemManager $storage,
        private array $config,
        private string $carpeta, // ruta absoluta de las miniaturas (public/thumbs)
        private string $urlBase, // URL pública de esa carpeta (/thumbs)
    ) {}

    public function habilitadas(): bool
    {
        $disk = config('filesystems.disks.'.$this->config['disk']);

        return filled($disk['key'] ?? null) && filled($disk['secret'] ?? null) && filled($disk['bucket'] ?? null);
    }

    /**
     * URL de la miniatura de cada código: el archivo estático si ya existe,
     * o la ruta que la genera la primera vez.
     *
     * @param  list<string>  $codigos
     * @return array<string, string> código => URL (solo los que tienen foto)
     */
    public function urls(array $codigos): array
    {
        if ($codigos === [] || ! $this->habilitadas()) {
            return [];
        }

        try {
            $mapa = $this->mapa();
        } catch (Throwable $e) {
            Log::warning('Fotos de productos no disponibles: '.$e->getMessage());

            return [];
        }

        $urls = [];
        foreach ($codigos as $codigo) {
            $archivo = $mapa[self::norm($codigo)] ?? null;
            if (! $archivo) {
                continue;
            }
            $nombre = self::nombreMiniatura($archivo);
            $urls[$codigo] = is_file($this->carpeta.DIRECTORY_SEPARATOR.$nombre)
                ? $this->urlBase.'/'.$nombre
                : route('imagenes.miniatura', ['codigo' => $codigo]);
        }

        return $urls;
    }

    /**
     * Ruta absoluta de la miniatura del código, generándola si no existe.
     * Devuelve null si el código no tiene foto.
     */
    public function miniatura(string $codigo): ?string
    {
        $archivo = $this->mapa()[self::norm($codigo)] ?? null;
        if (! $archivo) {
            return null;
        }

        $destino = $this->carpeta.DIRECTORY_SEPARATOR.self::nombreMiniatura($archivo);
        if (! is_file($destino)) {
            $this->generar($archivo, $destino);
        }

        return $destino;
    }

    /** @return array<string, string> código normalizado => archivo */
    public function mapa(): array
    {
        return $this->cache->remember('img_mapa', now()->addMinutes($this->config['mapa_minutos']), function () {
            $c = $this->config;

            return $this->db->connection($c['connection'])
                ->table($c['table'])
                ->whereNotNull($c['archivo'])
                ->where($c['archivo'], '<>', '')
                ->pluck($c['archivo'], $c['sku'])
                ->mapWithKeys(fn ($archivo, $sku) => [self::norm($sku) => trim($archivo)])
                ->all();
        });
    }

    private function generar(string $archivo, string $destino): void
    {
        $original = $this->storage->disk($this->config['disk'])->get($this->config['prefijo'].$archivo);
        if (! $original) {
            throw new RuntimeException("No existe en S3: {$archivo}");
        }

        $src = @imagecreatefromstring($original);
        if (! $src) {
            throw new RuntimeException("Imagen no válida: {$archivo}");
        }

        // Ajusta dentro de un cuadrado de N px conservando proporción y transparencia.
        $max = $this->config['miniatura_px'];
        [$w, $h] = [imagesx($src), imagesy($src)];
        $escala = min(1, $max / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $escala)), max(1, (int) round($h * $escala))];

        $thumb = imagecreatetruecolor($nw, $nh);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        if (! is_dir($this->carpeta)) {
            mkdir($this->carpeta, 0775, true);
        }

        // Escribe a un temporal y renombra: dos peticiones simultáneas no dejan un archivo a medias.
        $tmp = $destino.'.'.bin2hex(random_bytes(4)).'.tmp';
        imagewebp($thumb, $tmp, $this->config['miniatura_calidad']);
        rename($tmp, $destino);
    }

    private static function nombreMiniatura(string $archivo): string
    {
        return substr(md5($archivo), 0, 16).'.webp';
    }

    /** SQL Server ignora espacios finales y mayúsculas: se normaliza igual. */
    private static function norm(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }
}
