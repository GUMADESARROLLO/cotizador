@if (mb_strlen($q) < 2)
    @include('articulos._vacio')
@elseif ($error)
    <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <div class="empty-icon !text-[#ffd0d4]">
            <svg viewBox="0 0 24 24" class="size-8" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm0 13a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5ZM12 6a1 1 0 0 0-1 1v6a1 1 0 1 0 2 0V7a1 1 0 0 0-1-1Z"/></svg>
        </div>
        <p class="text-title font-extrabold text-white">Sin conexión al catálogo</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">No se pudo consultar SQL Server. Intenta de nuevo en un momento.</p>
    </div>
@elseif (! count($articulos))
    <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <p class="text-title font-extrabold text-white">Sin resultados</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">No hay artículos que coincidan con “{{ $q }}”.</p>
    </div>
@else
    <p class="section-label">{{ count($articulos) }} resultado(s)</p>
    <ul class="group-list animate-card-in">
        @foreach ($articulos as $a)
            @include('articulos._fila', ['a' => $a, 'enPedido' => $enCarrito[$a->codigo] ?? 0, 'imagen' => $imagenes[$a->codigo] ?? null, 'prioridad' => $loop->index < 4])
        @endforeach
    </ul>
@endif
