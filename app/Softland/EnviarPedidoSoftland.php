<?php

namespace App\Softland;

use App\Models\Pedido;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Throwable;

/**
 * Inserta en Softland (umk.PEDIDO + umk.PEDIDO_LINEA) un pedido ya guardado
 * en MySQL. Se ejecuta SOLO por acción manual (botón en el detalle del pedido).
 *
 * Todo ocurre en una única transacción de SQL Server: si algo falla, Softland
 * queda exactamente como estaba y el pedido de MySQL se marca con el error
 * para poder reintentar.
 */
class EnviarPedidoSoftland
{
    public function __construct(
        private ConnectionInterface $db,      // conexión SQL Server (Softland)
        private PedidoSoftland $preparador,   // arma encabezado y líneas
        private array $config,                // config/softland.php
    ) {}

    /**
     * @return string número de pedido asignado en Softland
     *
     * @throws DomainException con un mensaje para el usuario
     */
    public function enviar(Pedido $pedido): string
    {
        // 1. Reserva atómica en MySQL: evita que un doble clic lo envíe dos veces.
        $reservado = Pedido::query()
            ->whereKey($pedido->getKey())
            ->where(fn ($q) => $q->whereNull('softland_estado')->orWhere('softland_estado', Pedido::SOFTLAND_ERROR))
            ->update(['softland_estado' => Pedido::SOFTLAND_ENVIANDO, 'softland_error' => null]);

        if (! $reservado) {
            throw new DomainException('Este pedido ya fue enviado a Softland o se está enviando.');
        }

        try {
            $numero = $this->insertar($pedido->loadMissing('items'));
        } catch (Throwable $e) {
            $mensaje = $e instanceof DomainException ? $e->getMessage() : 'Softland rechazó el pedido: '.$e->getMessage();
            // La transacción se revirtió: el número anotado no se usó.
            $pedido->forceFill([
                'softland_estado' => Pedido::SOFTLAND_ERROR,
                'softland_pedido' => null,
                'softland_error' => Str::limit($mensaje, 2000),
            ])->save();

            throw new DomainException($mensaje, previous: $e);
        }

        $pedido->forceFill([
            'softland_estado' => Pedido::SOFTLAND_ENVIADO,
            'softland_pedido' => $numero,
            'softland_enviado_at' => now(),
            'softland_error' => null,
        ])->save();

        return $numero;
    }

    /**
     * Un pedido que quedó en "enviando" más de N minutos (el proceso se cayó a
     * mitad del envío) se resuelve consultando Softland: si el número anotado
     * existe para ese cliente, el envío sí llegó; si no, se marca como error
     * para poder reintentar sin duplicar.
     *
     * @return bool true si cambió el estado del pedido
     */
    public function revisarEnvioTrabado(Pedido $pedido): bool
    {
        $limite = now()->subMinutes($this->config['envio_trabado_minutos'] ?? 2);

        if ($pedido->softland_estado !== Pedido::SOFTLAND_ENVIANDO || $pedido->updated_at?->gt($limite)) {
            return false;
        }

        $llego = $pedido->softland_pedido && $this->db->table($this->config['esquema'].'.PEDIDO')
            ->where('PEDIDO', $pedido->softland_pedido)
            ->where('CLIENTE', $pedido->cliente_codigo)
            ->exists();

        $pedido->forceFill($llego
            ? ['softland_estado' => Pedido::SOFTLAND_ENVIADO, 'softland_enviado_at' => $pedido->updated_at, 'softland_error' => null]
            : [
                'softland_estado' => Pedido::SOFTLAND_ERROR,
                'softland_pedido' => null,
                'softland_error' => 'El envío anterior se interrumpió y el pedido no llegó a Softland. Puedes reintentar.',
            ])->save();

        return true;
    }

    private function insertar(Pedido $pedido): string
    {
        if (! $pedido->cliente_codigo) {
            throw new DomainException('El pedido no tiene código de cliente de Softland.');
        }

        $items = $pedido->items->map(fn ($i) => [
            'codigo' => (string) $i->codigo,
            'precio' => (float) $i->precio,
            'cantidad' => (float) $i->cantidad,
            'lote' => $i->lote,
        ])->values()->all();

        if ($items === []) {
            throw new DomainException('El pedido no tiene artículos.');
        }

        // No se valida existencia: se pueden pedir artículos agotados o más de lo
        // disponible en el lote (se completa con traslados antes de facturar).

        $e = $this->config['esquema'];
        $ahora = now();

        // Consecutivo elegido para este pedido (DESP-A, DESP-B…).
        $consecutivo = $pedido->consecutivo();

        return $this->db->transaction(function () use ($e, $items, $pedido, $ahora, $consecutivo) {
            // 2. Consecutivo con bloqueo de fila: nadie más toma este número hasta el commit.
            $valor = $this->db->table($e.'.CONSECUTIVO_FA')
                ->where('CODIGO_CONSECUTIVO', $consecutivo)
                ->lockForUpdate()
                ->value('VALOR_CONSECUTIVO');

            if ($valor === null) {
                throw new DomainException("No existe el consecutivo {$consecutivo} en Softland.");
            }

            $numero = $this->siguienteLibre(trim($valor));

            // Se anota en MySQL ANTES de insertar: si el proceso se cae a mitad del envío,
            // revisarEnvioTrabado() puede comprobar en Softland si este número llegó o no.
            Pedido::query()->whereKey($pedido->getKey())->update(['softland_pedido' => $numero]);

            // Igual que Softland: guarda el último número usado, la fecha y quién lo usó.
            // (El trigger EXCONSECUTIVO_FAIup repone RecordDate/UpdatedBy; NO se tocan
            // CreatedBy/CreateDate porque ese trigger rechaza cualquier cambio a ellas.)
            $this->db->table($e.'.CONSECUTIVO_FA')
                ->where('CODIGO_CONSECUTIVO', $consecutivo)
                ->update([
                    'VALOR_CONSECUTIVO' => $numero,
                    'RecordDate' => $this->fecha($ahora->format('Y-m-d H:i:s.v')),
                    'UpdatedBy' => $this->preparador->usuarioAuditoria(),
                ]);

            // 3. Encabezado y líneas (los mismos datos de la vista previa).
            $datos = $this->preparador->preparar($numero, $pedido->cliente_codigo, $items, $ahora, $pedido->notas, $pedido->fecha_pedido);

            $this->db->table($e.'.PEDIDO')->insert($this->valores($datos['encabezado']));
            foreach ($datos['lineas'] as $linea) {
                $this->db->table($e.'.PEDIDO_LINEA')->insert($this->valores($linea));
            }

            return $numero;
        });
    }

    /** Siguiente número del consecutivo que no exista ya como pedido (por si alguien lo usó a mano). */
    private function siguienteLibre(string $ultimo): string
    {
        $numero = ConsecutivoPedido::incrementar($ultimo);

        for ($i = 0; $i < 50; $i++) {
            $existe = $this->db->table($this->config['esquema'].'.PEDIDO')->where('PEDIDO', $numero)->exists();
            if (! $existe) {
                return $numero;
            }
            $numero = ConsecutivoPedido::incrementar($numero);
        }

        throw new DomainException('No se encontró un número de pedido libre en el consecutivo.');
    }

    /**
     * Columna => valor listo para insertar. Las fechas van en ISO 8601 ("T"),
     * que SQL Server interpreta igual sin importar el DATEFORMAT del servidor.
     *
     * @param  array<string, array{valor:mixed, origen:string}>  $campos
     */
    private function valores(array $campos): array
    {
        $fila = [];
        foreach ($campos as $columna => $x) {
            $v = $x['valor'];
            $fila[$columna] = match (true) {
                $v === 'NEWID()' => $this->rowPointer(),
                is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $v) === 1 => $this->fecha($v),
                default => $v,
            };
        }

        return $fila;
    }

    private function fecha(string $valor): string
    {
        return $this->db->getDriverName() === 'sqlsrv' ? str_replace(' ', 'T', $valor) : $valor;
    }

    private function rowPointer(): Expression|string
    {
        return $this->db->getDriverName() === 'sqlsrv' ? new Expression('NEWID()') : (string) Str::uuid();
    }
}
