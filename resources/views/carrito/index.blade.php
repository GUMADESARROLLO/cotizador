<x-layouts.app title="Pedido" slogan="Revisa cantidades y confirma" tab="pedido" :resumen="$resumen">
    @if (count($items))
        <x-slot:trailing>
            <form method="POST" action="{{ route('carrito.vaciar') }}"
                  onsubmit="return confirm('¿Vaciar el pedido? Se quitarán todos los artículos.')">
                @csrf @method('DELETE')
                <button class="pressable text-danger h-11 px-1 text-sm font-extrabold tracking-[0.06em] uppercase">Vaciar</button>
            </form>
        </x-slot:trailing>
    @endif

    <div data-cart-empty @if (count($items)) hidden @endif class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24" class="size-8" fill="currentColor" aria-hidden="true"><path d="M7 7V6a5 5 0 0 1 10 0v1h2.1a1 1 0 0 1 1 .92l1 12A2 2 0 0 1 19.1 22H4.9a2 2 0 0 1-2-2.08l1-12A1 1 0 0 1 4.9 7H7Zm2 0h6V6a3 3 0 0 0-6 0v1Z"/></svg>
        </div>
        <p class="text-title font-extrabold text-white">Tu pedido está vacío</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">Busca artículos y agrégalos; aquí verás cantidades y total.</p>
        <a href="{{ route('articulos.index') }}" class="btn-primary mt-6 w-auto">Buscar artículos</a>
    </div>

    @if (count($items))
        <div data-cart-content class="space-y-7">
            <section>
                <p class="section-label">Artículos</p>
                <ul class="group-list">
                    @foreach ($items as $i)
                        @php $ajustado = in_array($i['codigo'], session('ajustados', [])); @endphp
                        <li data-cart-row="{{ $i['codigo'] }}" class="flex flex-col gap-2.5 px-4 py-3.5 {{ $ajustado ? 'bg-warn-soft' : '' }}">
                            <div class="min-w-0">
                                <p class="text-body leading-snug font-semibold">{{ $i['descripcion'] }}</p>
                                <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="num text-[15px] font-bold">{{ $i['codigo'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="num text-[15px] font-bold">{{ dinero($i['precio']) }}</span> / {{ $i['unidad'] ?: 'u' }}
                                    <span aria-hidden="true">·</span>
                                    <x-existencia :existencia="$i['existencia']" :unidad="$i['unidad']" />
                                </p>
                                @if ($ajustado)
                                    <p class="text-foot text-warn mt-1 font-semibold">Ajustado a la existencia o precio actual.</p>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <x-stepper :codigo="$i['codigo']" :cantidad="$i['cantidad']" :max="floor($i['existencia'])" :agregar="false" />
                                <p class="price shrink-0" data-subtotal>{{ dinero($i['precio'] * $i['cantidad']) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section>
                <ul class="group-list">
                    <li class="flex items-center justify-between px-4 py-3"><span class="text-label-2 font-semibold">Artículos</span><span class="num text-xl font-bold" data-cart-lineas>{{ $resumen['lineas'] }}</span></li>
                    <li class="flex items-center justify-between px-4 py-3"><span class="text-label-2 font-semibold">Piezas</span><span class="num text-xl font-bold" data-cart-piezas>{{ cantidad($resumen['piezas']) }}</span></li>
                    <li class="bg-royal flex items-center justify-between px-4 py-3.5 text-white">
                        <span class="text-sm font-extrabold tracking-[0.1em] uppercase">Total</span>
                        <span class="num text-[32px] leading-none font-extrabold [text-shadow:0_0_24px_rgb(41_182_232/0.7)]" data-cart-total>{{ dinero($resumen['total']) }}</span>
                    </li>
                </ul>
                <p class="text-foot text-on-royal-soft px-1 pt-2">Las cantidades se limitan a la existencia actual y se vuelven a verificar al confirmar.</p>
            </section>

            <form method="POST" action="{{ route('pedidos.store') }}" class="space-y-6" data-confirm-form>
                @csrf
                <section>
                    <p class="section-label">Datos del pedido</p>
                    <div class="group-list">
                        <label class="flex items-center gap-3 px-4">
                            <span class="text-label-2 w-24 shrink-0 font-semibold">Cliente</span>
                            <input name="cliente" value="{{ old('cliente') }}" required maxlength="150" autocomplete="organization"
                                   placeholder="Requerido" class="placeholder:text-label-3 min-h-12 w-full bg-transparent py-3 font-medium outline-none">
                        </label>
                        <label class="flex items-center gap-3 px-4">
                            <span class="text-label-2 w-24 shrink-0 font-semibold">Contacto</span>
                            <input name="contacto" value="{{ old('contacto') }}" maxlength="150" autocomplete="name"
                                   placeholder="Opcional" class="placeholder:text-label-3 min-h-12 w-full bg-transparent py-3 font-medium outline-none">
                        </label>
                        <label class="block px-4">
                            <span class="sr-only">Notas</span>
                            <textarea name="notas" rows="3" maxlength="2000" placeholder="Notas (opcional)"
                                      class="placeholder:text-label-3 w-full resize-none bg-transparent py-3 font-medium outline-none">{{ old('notas') }}</textarea>
                        </label>
                    </div>
                    @error('cliente')<p class="text-foot px-1 pt-2 font-semibold text-[#ffd0d4]">⚠ {{ $message }}</p>@enderror
                </section>

                <button class="btn-primary">
                    Confirmar pedido · <span class="num text-xl" data-cart-total>{{ dinero($resumen['total']) }}</span>
                </button>
            </form>
        </div>
    @endif
</x-layouts.app>
