@props(['campo', 'orden' => null])
{{--
    Encabezado de columna ordenable (como DataTables): un clic ordena ascendente,
    otro descendente. El JS (app.js, ordenarTablas) decide cómo:
      - dentro de [data-listado]: lo pide al servidor (params o y d) sobre TODO el listado;
      - en una tabla con data-orden-local: reordena las filas en el navegador;
      - si no: recarga la página con ?o=…&d=… (p. ej. el historial).
--}}
@php($aria = \App\Support\Orden::aria($orden, $campo))
<th scope="col" aria-sort="{{ $aria }}" {{ $attributes }}>
    <button type="button" class="th-sort" data-orden="{{ $campo }}">
        <span>{{ $slot }}</span>
        <svg class="th-sort-ico" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path class="th-sort-up" d="m7 10 5-5 5 5" />
            <path class="th-sort-down" d="m7 14 5 5 5-5" />
        </svg>
    </button>
</th>
