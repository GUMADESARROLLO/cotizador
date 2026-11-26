<?php

namespace App\Softland;

use Illuminate\Database\ConnectionInterface;

/**
 * Consecutivos de pedido en Softland (umk.CONSECUTIVO_FA) y el número que
 * tendría el próximo pedido.
 *
 * Solo LEE: el número es tentativo, porque otro usuario de Softland puede
 * usarlo antes; al insertar se toma con bloqueo dentro de la transacción
 * (EnviarPedidoSoftland).
 *
 * Para F12 se puede elegir cualquier consecutivo que empiece con el prefijo
 * configurado (DESP-: DESP-A, DESP-B, DESP-C…); por defecto, $codigo.
 */
class ConsecutivoPedido
{
    public function __construct(
        private ?ConnectionInterface $db, // null = modo demo
        private string $esquema,
        private string $codigo,           // por defecto (config softland.consecutivo)
        private string $prefijo = '',     // los que se pueden elegir (config softland.consecutivos_prefijo)
    ) {}

    /** Consecutivo por defecto. */
    public function codigo(): string
    {
        return $this->codigo;
    }

    /**
     * Consecutivos que se pueden elegir, con su próximo número.
     *
     * @return array<string, array{codigo:string, descripcion:string, siguiente:string}>
     */
    public function disponibles(): array
    {
        if (! $this->db) {
            return [$this->codigo => ['codigo' => $this->codigo, 'descripcion' => 'Demo', 'siguiente' => self::incrementar('00000000')]];
        }

        $filas = $this->db->table($this->esquema.'.CONSECUTIVO_FA')
            ->when(
                $this->prefijo !== '',
                // El prefijo viene de la configuración (p. ej. "DESP-"), no del usuario.
                fn ($q) => $q->where('CODIGO_CONSECUTIVO', 'like', $this->prefijo.'%')
                    ->orWhere('CODIGO_CONSECUTIVO', $this->codigo),
                fn ($q) => $q->where('CODIGO_CONSECUTIVO', $this->codigo),
            )
            ->orderBy('CODIGO_CONSECUTIVO')
            ->get(['CODIGO_CONSECUTIVO', 'DESCRIPCION', 'VALOR_CONSECUTIVO']);

        $lista = [];
        foreach ($filas as $f) {
            $codigo = trim($f->CODIGO_CONSECUTIVO);
            $lista[$codigo] = [
                'codigo' => $codigo,
                'descripcion' => trim((string) $f->DESCRIPCION),
                'siguiente' => self::incrementar(trim((string) $f->VALOR_CONSECUTIVO)),
            ];
        }

        return $lista;
    }

    /** ¿Se puede usar este consecutivo para los pedidos? */
    public function permitido(string $codigo): bool
    {
        return $codigo === $this->codigo || ($this->prefijo !== '' && str_starts_with($codigo, $this->prefijo));
    }

    /** Próximo número de pedido del consecutivo (por defecto el configurado), o null si no existe. */
    public function siguiente(?string $codigo = null): ?string
    {
        if (! $this->db) {
            return self::incrementar('00000000');
        }

        $valor = $this->db->table($this->esquema.'.CONSECUTIVO_FA')
            ->where('CODIGO_CONSECUTIVO', $codigo ?? $this->codigo)
            ->value('VALOR_CONSECUTIVO');

        return $valor === null ? null : self::incrementar(trim($valor));
    }

    /**
     * Suma 1 a la última serie de dígitos conservando prefijo y ancho:
     * 00007835 → 00007836, PTV7-001128 → PTV7-001129, PDX10-000253 → PDX10-000254.
     */
    public static function incrementar(string $valor): string
    {
        if (! preg_match('/^(.*?)(\d+)$/', $valor, $m)) {
            return $valor.'1';
        }

        [, $prefijo, $numero] = $m;
        $siguiente = (string) ((int) $numero + 1);

        return $prefijo.str_pad($siguiente, strlen($numero), '0', STR_PAD_LEFT);
    }
}
