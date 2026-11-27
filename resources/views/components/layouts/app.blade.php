@props(['title' => 'Cotizador', 'sub' => null, 'section' => null, 'resumen' => null, 'slogan' => null, 'tab' => null])
@php
    // Compatibilidad con las páginas que aún usan slogan/tab: se dibujan sobre el panel azul "legacy".
    $legacy = ($slogan !== null || $tab !== null) && $sub === null && $section === null;
    $sub ??= $slogan;
    $section ??= $tab === 'cliente' ? 'clientes' : $tab;

    $carrito = null;
    $clienteActual = null;
    try {
        $carrito = app(\App\Pedidos\Carrito::class);
        $clienteActual = $carrito->cliente();
        $resumen ??= $carrito->resumen();
    } catch (\Throwable $e) {
        // sin sesión (páginas de error): la shell se dibuja sin cliente ni resumen
    }
    $resumen ??= ['lineas' => 0, 'piezas' => 0, 'total' => 0];

    $palabras = $clienteActual ? preg_split('/\s+/', trim($clienteActual->nombre), -1, PREG_SPLIT_NO_EMPTY) : [];
    if (($palabras[0] ?? '') !== '' && str_starts_with(mb_strtoupper($palabras[0]), 'FARM') && count($palabras) > 1) {
        array_shift($palabras);
    }
    $inicialesCliente = mb_strtoupper(mb_substr($palabras[0] ?? '', 0, 1).mb_substr($palabras[1] ?? '', 0, 1));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2b3b9e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="currency" content="{{ config('articulos.currency') }}">
    <title>{{ $title }} · Cotizador Unimark</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh overflow-x-hidden">
    <div class="app-shell" id="appShell">
        <script>
            // Aplica el estado colapsado antes de pintar para evitar parpadeo
            try { if (localStorage.getItem('cotSideCollapsed') === '1') document.getElementById('appShell').classList.add('side-collapsed'); } catch (e) {}
        </script>

        <x-sidebar :section="$section" :resumen="$resumen" />

        <div class="app-main">
            {{-- Barra superior: menú, cliente actual y resumen del pedido --}}
            <header class="topbar no-print">
                <button type="button" class="icon-btn" data-side-toggle aria-label="Menú" aria-controls="appSide" aria-expanded="false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>

                @isset($leading)
                    {{ $leading }}
                @endisset

                @if ($clienteActual)
                    <a href="{{ route('clientes.index') }}" class="top-cliente" title="Cambiar de cliente">
                        <span class="top-cliente-av" aria-hidden="true">{{ $inicialesCliente }}</span>
                        <span class="top-cliente-txt">{{ $clienteActual->nombre }}</span>
                        @if ($clienteActual->moroso)
                            <span class="tag tag-bad shrink-0">Moroso</span>
                        @endif
                    </a>
                @else
                    <a href="{{ route('clientes.index') }}" class="top-cliente top-cliente-vacio">Elige un cliente</a>
                @endif

                {{ $trailing ?? '' }}

                <a href="{{ route('carrito.index') }}" data-cart-top class="top-resumen" @if (! ($resumen['lineas'] ?? 0)) hidden @endif aria-label="Ver pedido">
                    <span><span class="num" data-cart-lineas>{{ $resumen['lineas'] ?? 0 }}</span> <span class="top-resumen-art">art.</span></span>
                    <span class="num" data-cart-total>{{ dinero($resumen['total'] ?? 0) }}</span>
                </a>
            </header>

            <main class="page">
                <div class="page-head">
                    <div class="min-w-0">
                        <h1 class="page-title">{{ $title }}</h1>
                        @if ($sub)
                            <p class="page-sub">{{ $sub }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="page-actions no-print">{{ $actions }}</div>
                    @endisset
                </div>

                @if ($legacy)
                    <div class="legacy-band">{{ $slot }}</div>
                @else
                    {{ $slot }}
                @endif
            </main>
        </div>
    </div>

    {{-- Barra de pestañas: solo en móvil/tablet (< lg) --}}
    <nav class="material-bar no-print border-line fixed inset-x-0 bottom-0 z-30 border-t lg:hidden"
         style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Secciones">
        <div class="mx-auto grid h-[56px] max-w-lg grid-cols-4">
            @php
                $tabs = [
                    ['clientes', route('clientes.index'), 'Cliente', 'M12 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 10c4.42 0 8 2.24 8 5v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1c0-2.76 3.58-5 8-5Zm0 2c-3.47 0-6 1.65-6 3v1h12v-1c0-1.35-2.53-3-6-3Z'],
                    ['articulos', route('articulos.index'), 'Artículos','M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z'],
                    ['pedido', route('carrito.index'), 'Pedido', 'M7 7V6a5 5 0 0 1 10 0v1h2.1a1 1 0 0 1 1 .92l1 12A2 2 0 0 1 19.1 22H4.9a2 2 0 0 1-2-2.08l1-12A1 1 0 0 1 4.9 7H7Zm2 0h6V6a3 3 0 0 0-6 0v1Zm-3.2 2-.9 11h14.2l-.9-11H17v1a1 1 0 1 1-2 0V9H9v1a1 1 0 1 1-2 0V9H5.8Z'],
                    ['historial', route('pedidos.index'), 'Historial', 'M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 2a1 1 0 0 1 1 1v4.59l2.7 2.7a1 1 0 0 1-1.4 1.42l-3-3A1 1 0 0 1 11 12V7a1 1 0 0 1 1-1Z'],
                ];
            @endphp
            @foreach ($tabs as [$key, $href, $label, $path])
                <a href="{{ $href }}" @if ($section === $key) aria-current="page" @endif
                   class="pressable relative flex flex-col items-center justify-center gap-0.5 text-[11px] font-bold tracking-[0.04em] uppercase {{ $section === $key ? 'text-royal' : 'text-ink-soft' }}">
                    <span class="relative">
                        <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                        @if ($key === 'pedido')
                            <span data-cart-badge
                                  class="num bg-cyan text-navy absolute -top-1.5 -right-3 min-w-[20px] rounded-full px-1 text-center text-[13px] leading-[20px] font-extrabold"
                                  @if (! ($resumen['lineas'] ?? 0)) hidden @endif>{{ $resumen['lineas'] ?? 0 }}</span>
                        @endif
                    </span>
                    {{ $label }}
                    @if ($section === $key)
                        <span class="bg-cyan absolute top-0 left-1/2 h-[3px] w-8 -translate-x-1/2 rounded-b-full" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>


    @if (session('aviso'))
        <script>window.__aviso = @json(session('aviso'));</script>
    @endif
</body>
</html>
