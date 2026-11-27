<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Datos del cliente para la cotización en Excel, recordados por código de cliente. */
#[Fillable(['cliente_codigo', 'nombre', 'atencion', 'cargo', 'telefono', 'fax'])]
class ClienteFicha extends Model
{
    public const CAMPOS = ['nombre', 'atencion', 'cargo', 'telefono', 'fax'];
}
