@props(['section' => null, 'resumen' => null])
@php
    $nombre = trim((string) config('clientes.vendedor_nombre'));
    $partes = preg_split('/\s+/', $nombre, -1, PREG_SPLIT_NO_EMPTY);
    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? 'V', 0, 1).mb_substr($partes[1] ?? '', 0, 1));

    // Íconos lucide (mismos trazos que SAC)
    $items = [
        ['clientes', route('clientes.index'), 'Clientes', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>'],
        ['articulos', route('articulos.index'), 'Artículos', '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path><path d="M12 22V12"></path><path d="m3.3 7 8.7 5 8.7-5"></path>'],
        ['pedido', route('carrito.index'), 'Pedido', '<rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"></path>'],
        ['historial', route('pedidos.index'), 'Historial', '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path>'],
    ];
@endphp
<aside class="side no-print" id="appSide" aria-label="Navegación principal">
    <svg class="side-waves" viewBox="0 0 300 190" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path d="M0 190 L0 140 C 80 118, 170 86, 300 52 L300 190 Z" fill="#3446b3" opacity=".55"></path>
        <path d="M0 122 C 82 100, 172 66, 300 30" fill="none" stroke="#29b6e8" stroke-width="4"></path>
        <path d="M0 108 C 84 86, 174 52, 300 16" fill="none" stroke="#ffffff" stroke-width="1.6" opacity=".9"></path>
        <path d="M110 190 C 170 160, 236 146, 300 140" fill="none" stroke="#ffffff" stroke-width="1.2" opacity=".35"></path>
    </svg>

    <a class="side-brand" href="{{ route('clientes.index') }}" aria-label="Cotizador Unimark, inicio">
        <img class="side-brand-full" src="/logo-20-white.png" alt="Unimark 20 años. Supliendo salud" width="196" height="54">
        <img class="side-brand-iso" src="/logo-20-iso-white.png" alt="Unimark 20 años" width="44" height="44">
    </a>

    <nav class="side-nav" aria-label="Secciones">
        @foreach ($items as [$key, $href, $label, $icono])
            <a href="{{ $href }}" title="{{ $label }}" class="side-item {{ $section === $key ? 'on' : '' }}" @if ($section === $key) aria-current="page" @endif>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icono !!}</svg>
                <span class="side-lbl">{{ $label }}</span>
                @if ($key === 'pedido')
                    <span data-cart-badge class="side-badge num side-lbl-keep" @if (! ($resumen['lineas'] ?? 0)) hidden @endif>{{ $resumen['lineas'] ?? 0 }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="side-foot">
        <span class="side-av" aria-hidden="true">{{ $iniciales }}</span>
        <div class="side-lbl min-w-0">
            <div class="side-foot-name">{{ $nombre ?: 'Vendedor' }}</div>
            <div class="side-foot-sub">Vendedor {{ config('clientes.vendedor') }}</div>
        </div>
    </div>
</aside>
<div class="side-scrim no-print" data-side-close></div>
