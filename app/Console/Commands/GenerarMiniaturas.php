<?php

namespace App\Console\Commands;

use App\Catalogo\Imagenes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('imagenes:miniaturas')]
#[Description('Genera de antemano las miniaturas WebP de todos los productos con foto')]
class GenerarMiniaturas extends Command
{
    public function handle(Imagenes $imagenes): int
    {
        if (! $imagenes->habilitadas()) {
            $this->error('Faltan las credenciales S3 (AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_BUCKET).');

            return self::FAILURE;
        }

        Cache::forget('img_mapa'); // toma la lista más reciente de tbl_product
        $codigos = array_keys($imagenes->mapa());
        $fallas = [];

        $this->withProgressBar($codigos, function (string $codigo) use ($imagenes, &$fallas) {
            try {
                $imagenes->miniatura($codigo);
            } catch (Throwable $e) {
                $fallas[$codigo] = $e->getMessage();
            }
        });

        $this->newLine(2);
        $this->info((count($codigos) - count($fallas)).' miniaturas listas en public/thumbs.');
        foreach ($fallas as $codigo => $error) {
            $this->warn("  {$codigo}: {$error}");
        }

        return self::SUCCESS;
    }
}
