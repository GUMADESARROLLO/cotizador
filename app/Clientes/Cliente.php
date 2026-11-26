<?php

namespace App\Clientes;

final readonly class Cliente
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public string $direccion,
        public string $ruc,
        public string $vendedor,
        public bool $moroso,
        public float $credito,
        public float $saldo,
        public float $disponible,
    ) {}

    public static function fromRow(object|array $row): self
    {
        $row = (object) $row;

        return new self(
            codigo: trim((string) $row->codigo),
            nombre: trim((string) $row->nombre),
            direccion: trim((string) ($row->direccion ?? '')),
            ruc: self::rucValido((string) ($row->ruc ?? '')),
            vendedor: trim((string) ($row->vendedor ?? '')),
            moroso: strtoupper(trim((string) ($row->moroso ?? 'N'))) === 'S',
            credito: round((float) ($row->credito ?? 0), 2),
            saldo: round((float) ($row->saldo ?? 0), 2),
            disponible: round((float) ($row->disponible ?? 0), 2),
        );
    }

    /**
     * En GMV_Clientes más de la mitad de los RUC son "N/D" o texto copiado del
     * nombre. Solo se conserva si tiene forma de RUC (8–20 letras/números).
     */
    private static function rucValido(string $ruc): string
    {
        $ruc = strtoupper(trim($ruc));

        return preg_match('/^[A-Z0-9-]{8,20}$/', $ruc) ? $ruc : '';
    }

    /** Copia que se guarda en sesión mientras se arma el pedido. */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
