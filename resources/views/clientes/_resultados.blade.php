@if (mb_strlen($q) < 2)
    @include('clientes._vacio')
@elseif ($error)
    <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <p class="text-title font-extrabold text-white">Sin conexión a clientes</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">No se pudo consultar SQL Server. Intenta de nuevo en un momento.</p>
    </div>
@elseif (! count($clientes))
    <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <p class="text-title font-extrabold text-white">Sin resultados</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">No hay clientes activos que coincidan con “{{ $q }}”.</p>
    </div>
@else
    <p class="section-label">{{ count($clientes) }} cliente(s)</p>
    <ul class="group-list animate-card-in">
        @foreach ($clientes as $c)
            <li>
                <form method="POST" action="{{ route('clientes.seleccionar') }}">
                    @csrf
                    <input type="hidden" name="codigo" value="{{ $c->codigo }}">
                    <button class="pressable active:bg-mist flex w-full items-center gap-3 px-4 py-3.5 text-left {{ $actual === $c->codigo ? 'bg-cyan-soft' : '' }}">
                        <div class="min-w-0 flex-1">
                            <p class="leading-snug font-bold">{{ $c->nombre }}</p>
                            <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="num text-[15px] font-bold">{{ $c->codigo }}</span>
                                @if ($c->ruc)<span aria-hidden="true">·</span><span>RUC {{ $c->ruc }}</span>@endif
                            </p>
                            @if ($c->direccion)
                                <p class="text-foot text-label-2 mt-1 line-clamp-1">{{ $c->direccion }}</p>
                            @endif
                            <p class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if ($c->moroso)
                                    <span class="badge bg-danger-soft text-danger uppercase">Moroso</span>
                                @endif
                                @if ($c->disponible > 0)
                                    <span class="badge bg-cyan-soft text-cyan-ink">Disponible <span class="num text-[13px]">{{ dinero($c->disponible) }}</span></span>
                                @else
                                    <span class="badge bg-warn-soft text-warn">Sin crédito disponible</span>
                                @endif
                            </p>
                        </div>
                        @if ($actual === $c->codigo)
                            <span class="text-cyan-ink text-[11px] font-extrabold tracking-[0.1em] uppercase">Actual</span>
                        @endif
                        <svg viewBox="0 0 24 24" class="text-label-3 size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
