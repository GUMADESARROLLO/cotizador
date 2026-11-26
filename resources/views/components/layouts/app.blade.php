@props(['title' => 'Cotizador', 'slogan' => null, 'tab' => null, 'resumen' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
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
<body class="flex min-h-dvh flex-col overflow-x-hidden">
    {{-- Barra superior blanca con el logo --}}
    <header data-scroll-edge class="top-bar no-print sticky top-0 z-30" style="padding-top: env(safe-area-inset-top)">
        <div class="container-x flex h-[60px] items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                @isset($leading)
                    {{ $leading }}
                @else
                    <a href="{{ route('articulos.index') }}" class="shrink-0" aria-label="Inicio">
                        <img src="/logo-unimark-20.webp" alt="Unimark, 20 años. Supliendo salud" width="1200" height="364" class="h-10 w-auto" decoding="async">
                    </a>
                @endisset
                <b data-compact-title class="font-display text-royal truncate text-sm leading-none font-extrabold tracking-[0.06em] uppercase">{{ $title }}</b>
            </div>
            <div class="flex shrink-0 items-center">{{ $trailing ?? '' }}</div>
        </div>
    </header>

    <main class="flex flex-1 flex-col">
        {{-- Encabezado blanco --}}
        <div class="hero">
            <div class="container-x pt-4 pb-1">
                <p class="text-cyan-ink text-[12px] font-extrabold tracking-[0.14em] uppercase">Cotizador Unimark</p>
                <h1 class="hero-title mt-1">{{ $title }}</h1>
                @if ($slogan)
                    <p class="hero-slogan">{{ $slogan }}</p>
                @endif
            </div>
        </div>

        {{-- Franja azul con ondas: aquí vive el contenido --}}
        <div class="band flex flex-1 flex-col">
            <x-band-waves />
            <div class="container-x flex-1 pt-2 pb-8">
                {{ $slot }}
            </div>

            <footer class="no-print pb-safe-bar border-t border-white/20">
                <div class="container-x text-on-royal-soft flex flex-wrap items-center justify-between gap-x-6 gap-y-2 pt-3 text-[13px]">
                    <p class="m-0"><strong class="font-bold text-white">Unimark</strong> · Supliendo salud desde hace 20 años</p>
                    <ul class="foot-links m-0 flex list-none flex-wrap p-0">
                        <li>Existencias en línea</li>
                        <li>Precios {{ config('articulos.currency') }}</li>
                    </ul>
                </div>
                <svg class="mt-2 block h-[18px] w-full" viewBox="0 0 1440 40" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 34 C 400 30, 900 20, 1440 4" fill="none" stroke="#29b6e8" stroke-width="3"></path>
                    <path d="M200 40 C 600 34, 1000 26, 1440 14" fill="none" stroke="#ffffff" stroke-width="2" opacity="0.85"></path>
                </svg>
            </footer>
        </div>
    </main>

    {{-- Barra de pestañas: material blanco translúcido --}}
    <nav class="material-bar no-print border-line fixed inset-x-0 bottom-0 z-30 border-t"
         style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Secciones">
        <div class="mx-auto grid h-[56px] max-w-md grid-cols-3">
            @php
                $tabs = [
                    ['buscar', route('articulos.index'), 'Buscar', 'M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z'],
                    ['pedido', route('carrito.index'), 'Pedido', 'M7 7V6a5 5 0 0 1 10 0v1h2.1a1 1 0 0 1 1 .92l1 12A2 2 0 0 1 19.1 22H4.9a2 2 0 0 1-2-2.08l1-12A1 1 0 0 1 4.9 7H7Zm2 0h6V6a3 3 0 0 0-6 0v1Zm-3.2 2-.9 11h14.2l-.9-11H17v1a1 1 0 1 1-2 0V9H9v1a1 1 0 1 1-2 0V9H5.8Z'],
                    ['historial', route('pedidos.index'), 'Historial', 'M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 2a1 1 0 0 1 1 1v4.59l2.7 2.7a1 1 0 0 1-1.4 1.42l-3-3A1 1 0 0 1 11 12V7a1 1 0 0 1 1-1Z'],
                ];
            @endphp
            @foreach ($tabs as [$key, $href, $label, $path])
                <a href="{{ $href }}" @if ($tab === $key) aria-current="page" @endif
                   class="pressable relative flex flex-col items-center justify-center gap-0.5 text-[11px] font-bold tracking-[0.04em] uppercase {{ $tab === $key ? 'text-royal' : 'text-ink-soft' }}">
                    <span class="relative">
                        <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                        @if ($key === 'pedido')
                            <span data-cart-badge
                                  class="num bg-cyan text-navy absolute -top-1.5 -right-3 min-w-[20px] rounded-full px-1 text-center text-[13px] leading-[20px] font-extrabold"
                                  @if (! ($resumen['lineas'] ?? 0)) hidden @endif>{{ $resumen['lineas'] ?? 0 }}</span>
                        @endif
                    </span>
                    {{ $label }}
                    @if ($tab === $key)
                        <span class="bg-cyan absolute top-0 left-1/2 h-[3px] w-8 -translate-x-1/2 rounded-b-full" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Aviso (HUD) --}}
    <div data-toast role="status" aria-live="polite" hidden
         class="toast text-ink fixed left-1/2 z-50 max-w-[calc(100%-2rem)] -translate-x-1/2 rounded-2xl bg-white px-4 py-3 text-sub font-semibold shadow-[0_0_0_2px_var(--color-cyan),0_24px_50px_-18px_rgb(41_182_232/0.85)]"
         style="top: calc(env(safe-area-inset-top) + 4rem)"></div>

    @if (session('aviso'))
        <script>window.__aviso = @json(session('aviso'));</script>
    @endif
</body>
</html>
