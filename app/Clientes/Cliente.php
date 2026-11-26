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
        // Datos que el pedido de Softland copia de la ficha del cliente.
        public string $condicionPago = '',
        public string $nivelPrecio = '',
        public string $monedaNivel = 'L', // moneda de los precios: L = córdobas, D = dólares
        public string $moneda = 'L',      // moneda del crédito/saldo del cliente
    ) {}

    /**
     * Arma el cliente desde una fila de Softland.umk.CLIENTE.
     *
     * En Softland el RUC viene dentro del nombre ("DISTRIBUIDORA X, S.A. - RUC J031…",
     * "FARMACIA Y-RUC 0422…", "FARMACIA Z RUC 001-090161-0051S"): se separa aquí.
     * Si la fila ya trae "ruc" (p. ej. datos demo) se usa ese.
     */
    public static function fromRow(object|array $row): self
    {
        $row = (object) $row;

        [$nombre, $rucDelNombre] = self::separarRuc((string) $row->nombre);
        $credito = round((float) ($row->credito ?? 0), 2);
        $saldo = round((float) ($row->saldo ?? 0), 2);

        return new self(
            codigo: trim((string) $row->codigo),
            nombre: $nombre,
            direccion: trim((string) preg_replace('/^\s*DETALLE:\s*/iu', '', (string) ($row->direccion ?? ''))),
            ruc: self::rucValido(isset($row->ruc) ? (string) $row->ruc : $rucDelNombre),
            vendedor: trim((string) ($row->vendedor ?? '')),
            moroso: strtoupper(trim((string) ($row->moroso ?? 'N'))) === 'S',
            credito: $credito,
            saldo: $saldo,
            disponible: isset($row->disponible) ? round((float) $row->disponible, 2) : round($credito - $saldo, 2),
            condicionPago: trim((string) ($row->condicion_pago ?? '')),
            nivelPrecio: trim((string) ($row->nivel_precio ?? '')),
            monedaNivel: strtoupper(trim((string) ($row->moneda_nivel ?? 'L'))) === 'D' ? 'D' : 'L',
            // En la ficha la moneda viene como COR / DOL.
            moneda: in_array(strtoupper(trim((string) ($row->moneda ?? ''))), ['DOL', 'D', 'USD'], true) ? 'D' : 'L',
        );
    }

    /**
     * El aviso de crédito solo tiene sentido si precios y crédito están en la
     * misma moneda (sin tipo de cambio no se pueden comparar).
     */
    public function creditoComparable(): bool
    {
        return $this->moneda === $this->monedaNivel;
    }

    /** @return array{0:string,1:string} [nombre sin RUC, RUC encontrado o ''] */
    private static function separarRuc(string $nombre): array
    {
        $nombre = trim($nombre);

        // "RUC" o "RUR" (error de digitación frecuente) como palabra, seguido del número.
        if (preg_match('/^(.*?)[\s\-]*(?<![A-Z])RU[CR](?![A-Z])[\s.:\-]*(\S.*)$/iu', $nombre, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$nombre, ''];
    }

    /** Solo se conserva si tiene forma de RUC (8–20 letras/números/guiones). */
    private static function rucValido(string $ruc): string
    {
        $ruc = strtoupper(str_replace(' ', '', trim($ruc)));

        return preg_match('/^[A-Z0-9-]{8,20}$/', $ruc) ? $ruc : '';
    }

    /** Copia que se guarda en sesión mientras se arma el pedido. */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        // Ignora claves desconocidas (sesiones guardadas con otra versión).
        $campos = array_keys(get_class_vars(self::class));

        return new self(...array_intersect_key($data, array_flip($campos)));
    }
}
