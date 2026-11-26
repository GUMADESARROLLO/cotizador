<?php

namespace App\Softland;

use Illuminate\Database\ConnectionInterface;

/**
 * Número que tendrá el próximo pedido en Softland.
 *
 * Solo LEE umk.CONSECUTIVO_FA para mostrarlo: es tentativo, porque otro
 * usuario de Softland puede usar ese número antes de que se confirme.
 * Al insertar el pedido habrá que tomarlo con bloqueo dentro de la transacción.
 */
class ConsecutivoPedido
{
    public function __construct(
        private ?ConnectionInterface $db, // null = modo demo
        private string $esquema,
        private string $codigo,
    ) {}

    public function codigo(): string
    {
        return $this->codigo;
    }

    /** Próximo número de pedido, o null si el consecutivo no existe. */
    public function siguiente(): ?string
    {
        if (! $this->db) {
            return self::incrementar('00000000');
        }

        $valor = $this->db->table($this->esquema.'.CONSECUTIVO_FA')
            ->where('CODIGO_CONSECUTIVO', $this->codigo)
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
