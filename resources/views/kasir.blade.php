<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - {{ $kasirData['toko']['nama'] ?? 'Toko PKL' }}</title>
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    @isset($kasirData)
        <script>window.KASIR_DATA = @json($kasirData);</script>
    @endisset
    @vite(['resources/css/app.css', 'resources/js/kasir/kasir.js'])
    <style>
        body { font-family: 'Satoshi', ui-sans-serif, system-ui, sans-serif; }
        .kbd-shortcut {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            padding: 0.15rem 0.5rem;
            background: #f4f4f5;
            border: 1px solid #e4e4e7;
            border-bottom-width: 2px;
            border-radius: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: #3f3f46;
            font-family: ui-monospace, 'Cascadia Code', Menlo, monospace;
            line-height: 1.25;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 99px; }
        ::-webkit-scrollbar-track { background: transparent; }

        @media (prefers-reduced-motion: no-preference) {
            .anim-fade-up {
                animation: fade-up .45s cubic-bezier(.16, 1, .3, 1) both;
                animation-delay: calc(var(--i, 0) * 35ms);
            }
            .anim-scale-in { animation: scale-in .22s cubic-bezier(.16, 1, .3, 1) both; }
            .anim-backdrop { animation: fade .2s ease-out both; }
            .anim-toast { animation: toast-up .3s cubic-bezier(.16, 1, .3, 1) both; }
            .anim-pop { animation: pop .25s cubic-bezier(.16, 1, .3, 1); }
        }
        @keyframes fade-up { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes scale-in { from { opacity: 0; transform: scale(.97) translateY(-4px); } to { opacity: 1; transform: none; } }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes toast-up { from { opacity: 0; transform: translate(-50%, 12px); } to { opacity: 1; transform: translate(-50%, 0); } }
        @keyframes pop { 0% { transform: scale(1); } 40% { transform: scale(1.05); } 100% { transform: scale(1); } }

        @media print {
            body * { visibility: hidden; }
            #modal-struk, #modal-struk * { visibility: visible; }
            #modal-struk { position: absolute; inset: 0; background: white; overflow: visible; max-height: none; }
            #modal-struk > div { max-height: none !important; overflow: visible !important; box-shadow: none !important; }
            #struk-actions { display: none !important; }
        }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased">

    {{-- LOADING SKELETON --}}
    <div id="loading" class="fixed inset-0 flex h-dvh">
        <div class="hidden lg:flex flex-col items-center gap-5 py-6 shrink-0 w-[92px] bg-white border-r border-zinc-200">
            <div class="flex flex-col items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-zinc-200 animate-pulse"></div>
                <div class="w-10 h-2 rounded bg-zinc-100 animate-pulse"></div>
            </div>
            <div class="w-full px-2 space-y-2">
                <div class="h-12 rounded-xl bg-zinc-200/50 animate-pulse"></div>
                <div class="h-12 rounded-xl bg-zinc-200/50 animate-pulse"></div>
            </div>
        </div>
        <div class="flex-1 flex overflow-hidden">
            <div class="flex-1 px-8 pt-7">
                <div class="max-w-5xl mx-auto space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="h-12 flex-1 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                        <div class="h-12 w-40 shrink-0 rounded-xl bg-zinc-200/50 animate-pulse"></div>
                    </div>
                    <div class="h-9 w-72 rounded-xl bg-zinc-200/50 animate-pulse"></div>
                    <div class="grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 gap-4">
                        <div class="h-60 rounded-2xl bg-white border border-zinc-200 p-3.5 flex flex-col justify-between">
                            <div class="h-36 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                            <div class="space-y-2 mt-2">
                                <div class="h-4 w-3/4 rounded bg-zinc-200 animate-pulse"></div>
                                <div class="flex justify-between items-center pt-2">
                                    <div class="h-5 w-20 rounded bg-zinc-200 animate-pulse"></div>
                                    <div class="h-4 w-12 rounded bg-zinc-200 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                        <div class="h-60 rounded-2xl bg-white border border-zinc-200 p-3.5 flex flex-col justify-between [animation-delay:100ms]">
                            <div class="h-36 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                            <div class="space-y-2 mt-2">
                                <div class="h-4 w-3/4 rounded bg-zinc-200 animate-pulse"></div>
                                <div class="flex justify-between items-center pt-2">
                                    <div class="h-5 w-20 rounded bg-zinc-200 animate-pulse"></div>
                                    <div class="h-4 w-12 rounded bg-zinc-200 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                        <div class="h-60 rounded-2xl bg-white border border-zinc-200 p-3.5 flex flex-col justify-between [animation-delay:200ms]">
                            <div class="h-36 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                            <div class="space-y-2 mt-2">
                                <div class="h-4 w-3/4 rounded bg-zinc-200 animate-pulse"></div>
                                <div class="flex justify-between items-center pt-2">
                                    <div class="h-5 w-20 rounded bg-zinc-200 animate-pulse"></div>
                                    <div class="h-4 w-12 rounded bg-zinc-200 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                        <div class="h-60 rounded-2xl bg-white border border-zinc-200 p-3.5 flex flex-col justify-between [animation-delay:300ms] hidden 2xl:flex">
                            <div class="h-36 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                            <div class="space-y-2 mt-2">
                                <div class="h-4 w-3/4 rounded bg-zinc-200 animate-pulse"></div>
                                <div class="flex justify-between items-center pt-2">
                                    <div class="h-5 w-20 rounded bg-zinc-200 animate-pulse"></div>
                                    <div class="h-4 w-12 rounded bg-zinc-200 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-[360px] xl:w-[420px] shrink-0 bg-white border-l border-zinc-200"></div>
        </div>
    </div>

    {{-- MAIN KASIR APP --}}
    <div id="kasir-app" class="hidden h-dvh flex overflow-hidden">

        {{-- SIDEBAR KIRI (desktop) --}}
        <aside class="hidden lg:flex flex-col items-center py-5 shrink-0 w-[92px] bg-white border-r border-zinc-200">
            <div class="flex flex-col items-center gap-1.5 px-1">
                <div class="w-9 h-9 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-black text-sm select-none">K</div>
                <p class="text-[11px] font-bold text-zinc-700 text-center leading-tight truncate w-full select-none" title="{{ $kasirData['toko']['nama'] ?? 'Toko' }}">{{ $kasirData['toko']['nama'] ?? 'Toko' }}</p>
                <span id="badge-mock" class="hidden text-[7px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded-full">
                    Simulasi
                </span>
            </div>

            <div class="flex flex-col items-center gap-1.5 mt-6">
                <button id="btn-panduan-shortcut" type="button" title="Panduan shortcut (?)" aria-label="Panduan shortcut"
                    class="w-full flex flex-col items-center gap-1 rounded-xl px-1 py-2 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M9 14h6"/>
                    </svg>
                    <span class="text-xs font-semibold leading-none">Panduan</span>
                </button>

                <button id="btn-riwayat" type="button" title="Riwayat transaksi" aria-label="Riwayat transaksi"
                    class="w-full flex flex-col items-center gap-1 rounded-xl px-1 py-2 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9 -9a9.75 9.75 0 0 0 -6.74 2.74l-2.26 -2"/>
                        <path d="M3 3v5h5"/>
                        <path d="M12 7v5l3 3"/>
                    </svg>
                    <span class="text-xs font-semibold leading-none">Riwayat</span>
                </button>
            </div>

            <div class="flex-1"></div>

            <form method="POST" action="{{ route('kasir.logout') }}" class="w-full flex justify-center">
                @csrf
                <button type="submit" title="Logout" aria-label="Logout"
                    class="w-[calc(100%-16px)] flex flex-col items-center gap-1 rounded-xl px-1 py-2 text-zinc-500 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                    <svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                        <path d="M9 12h12l-3 -3"/>
                        <path d="M18 15l3 -3"/>
                    </svg>
                    <span class="text-xs font-semibold leading-none">Logout</span>
                </button>
            </form>
        </aside>

        <div class="flex-1 flex overflow-hidden">

            {{-- BACKDROP KERANJANG (mobile) --}}
            <div id="backdrop-cart" class="hidden lg:hidden fixed inset-0 z-20 bg-zinc-950/40 backdrop-blur-sm"></div>

            {{-- KIRI: DAFTAR PRODUK --}}
            <main class="flex-1 flex flex-col min-w-0 px-4 lg:px-8 pt-7 overflow-hidden">
                <div class="anim-fade-up max-w-5xl w-full mx-auto flex flex-col flex-1 overflow-hidden" style="--i: 1">

                    {{-- TOOLBAR (mobile only): tombol aksi kasir --}}
                    <div class="lg:hidden flex items-center gap-3">
                        {{-- Tombol aksi mobile-only (muncul di atas area konten karena sidebar tersembunyi) --}}
                        <div class="lg:hidden flex items-center gap-2 shrink-0">
                            <button id="btn-mobile-shortcut" type="button" title="Panduan shortcut (?)" aria-label="Panduan shortcut"
                                class="w-9 h-9 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400 hover:bg-zinc-50 transition-all duration-200 cursor-pointer">
                                <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="6" width="20" height="12" rx="2"/>
                                    <path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M9 14h6"/>
                                </svg>
                            </button>
                            <button id="btn-mobile-riwayat" type="button" title="Riwayat transaksi" aria-label="Riwayat transaksi"
                                class="w-9 h-9 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400 hover:bg-zinc-50 transition-all duration-200 cursor-pointer">
                                <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 1 0 9 -9a9.75 9.75 0 0 0 -6.74 2.74l-2.26 -2"/>
                                    <path d="M3 3v5h5"/>
                                    <path d="M12 7v5l3 3"/>
                                </svg>
                            </button>
                            <form method="POST" action="{{ route('kasir.logout') }}">
                                @csrf
                                <button type="submit" title="Logout" aria-label="Logout"
                                    class="w-9 h-9 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-500 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-all duration-200 cursor-pointer">
                                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                                        <path d="M9 12h12l-3 -3"/>
                                        <path d="M18 15l3 -3"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                        <div class="flex-1"></div>
                    </div>

                    <div class="flex items-center gap-3 mt-2">
                        <div class="relative flex-1 min-w-0">
                            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-zinc-400 pointer-events-none"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>
                            </svg>
                            <input id="input-search" type="text" placeholder="Cari nama, kode, atau scan barcode"
                                class="w-full h-12 border border-zinc-200 rounded-xl pl-11 pr-10 text-sm font-medium bg-white placeholder:text-zinc-500 placeholder:font-normal shadow-xs shadow-zinc-100 focus:outline-none focus:border-zinc-900 transition-colors">
                            <button id="btn-clear-search" type="button" aria-label="Bersihkan pencarian" title="Bersihkan pencarian" class="absolute right-3 top-1/2 -translate-y-1/2 w-7 h-7 text-zinc-500 hover:text-zinc-900 hidden flex items-center justify-center transition-colors">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>

                        <div id="dd-gudang" class="relative shrink-0">
                            <button type="button" data-dd-btn aria-haspopup="listbox" aria-expanded="false"
                                class="flex items-center gap-2 border border-zinc-200 rounded-xl bg-white pl-3 pr-2.5 lg:pl-4 lg:pr-3 py-2.5 h-12 max-w-[150px] lg:max-w-[220px] hover:border-zinc-400 transition-colors cursor-pointer">
                                <span data-dd-value class="text-sm font-bold truncate"></span>
                                <svg data-dd-chevron class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200"
                                    viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4"/>
                                </svg>
                            </button>
                        </div>

                        <div id="gudang-stok-info" class="hidden lg:flex shrink-0 flex-col justify-center text-[11px] leading-tight pr-1">
                            <span id="gudang-stok-produk" class="font-bold text-zinc-500"></span>
                            <span id="gudang-stok-total" class="text-zinc-400 tabular-nums"></span>
                        </div>
                    </div>

                    <div id="filter-jenis" class="inline-flex w-fit max-w-full overflow-x-auto gap-1 bg-zinc-200/60 rounded-xl p-1 mt-4 mb-4"></div>

                    <div id="grid-produk"
                        class="flex-1 overflow-y-auto grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 gap-4 content-start pt-3 pb-8 px-2"></div>
                </div>
            </main>

            {{-- KANAN: KERANJANG --}}
            <aside id="cart-drawer"
                class="fixed inset-y-0 right-0 z-30 w-[360px] max-w-[92vw] xl:w-[420px] shrink-0 bg-white border-l border-zinc-200 flex flex-col translate-x-full transition-transform duration-300 ease-out lg:static lg:translate-x-0 lg:transition-none">
                
                {{-- Header --}}
                <div class="h-16 shrink-0 px-6 border-b border-zinc-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold tracking-tight text-zinc-900">Pesanan</h2>
                        <span id="badge-cart-count"
                            class="hidden min-w-5 h-5 px-1.5 rounded-full bg-zinc-900 text-white text-[11px] font-bold flex items-center justify-center tabular-nums"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="btn-reset" type="button" title="Kosongkan keranjang"
                            class="text-xs font-semibold text-zinc-400 hover:text-red-600 transition-colors cursor-pointer px-1 py-0.5">Kosongkan</button>
                    </div>
                </div>

                {{-- Daftar Item Keranjang --}}
                <div id="cart-items" class="flex-1 overflow-y-auto px-6 py-3 space-y-2.5"></div>

                {{-- Ringkasan & Pembayaran --}}
                <div class="shrink-0 border-t border-zinc-200 bg-white px-6 pt-4 pb-5 space-y-3.5">
                    {{-- Summary Breakdown --}}
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-500 font-medium">Subtotal</span>
                            <span id="lbl-total" class="font-bold text-zinc-900 tabular-nums">Rp 0</span>
                        </div>
                        <div id="row-diskon-nota" class="hidden flex justify-between items-center gap-3">
                            <span class="text-zinc-500 font-medium">Diskon</span>
                            <div class="flex items-center gap-2">
                                <input id="input-diskon" type="text" inputmode="numeric" placeholder="0%"
                                    class="w-14 text-right text-xs font-semibold bg-white border border-zinc-200 rounded-md px-2 py-1 tabular-nums placeholder:font-normal placeholder:text-zinc-300 focus:outline-none focus:border-zinc-900 transition-colors" title="Diskon dalam %">
                                <span id="lbl-diskon-nota" class="font-bold text-zinc-900 tabular-nums text-sm">-Rp 0</span>
                            </div>
                        </div>
                        <div id="row-potongan-barang" class="hidden flex justify-between items-center">
                            <span class="text-zinc-500 font-medium">Potongan Barang</span>
                            <span id="lbl-potongan-barang" class="font-bold text-zinc-900 tabular-nums"></span>
                        </div>
                        <div class="border-t border-zinc-200/80 pt-2 mt-1.5 flex justify-between items-baseline">
                            <span class="font-bold text-zinc-900 text-base">Total</span>
                            <span id="lbl-neto" class="inline-block origin-right text-xl font-black tracking-tight text-zinc-900 tabular-nums">Rp 0</span>
                        </div>
                    </div>

                    {{-- Payments Section --}}
                    <div class="pt-2 border-t border-zinc-200/80 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-zinc-900 tracking-tight">Pembayaran</h3>
                            <button id="btn-toggle-payment" type="button" title="Rincian pembayaran" aria-label="Rincian pembayaran"
                                class="w-6 h-6 flex items-center justify-center rounded-md text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                                <svg id="icon-toggle-payment" class="w-4 h-4 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                            </button>
                        </div>

                        <div class="flex gap-1 bg-zinc-100 rounded-lg p-1">
                            @foreach ([
                                'tunai' => 'Tunai',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS',
                            ] as $val => $label)
                                <label class="payment-method-pill flex-1 flex items-center justify-center text-xs font-bold rounded-md py-1.5 px-1 cursor-pointer transition-all text-center select-none
                                              {{ $val === 'tunai' ? 'bg-zinc-900 text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900' }}
                                              has-checked:bg-zinc-900 has-checked:text-white has-checked:shadow-xs">
                                    <input type="radio" name="jenis_pembayaran" value="{{ $val }}"
                                        class="hidden" {{ $val === 'tunai' ? 'checked' : '' }}>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        {{-- Details per payment method --}}
                        <div id="payment-details-container" class="space-y-2.5 pt-0.5">
                            <div id="row-tunai" class="space-y-2 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-zinc-600 font-medium">Uang diterima</span>
                                    <input id="input-bayar" type="text" inputmode="numeric" placeholder="Rp 0"
                                        class="w-32 text-right text-xs font-bold bg-white border border-zinc-200 rounded-lg px-2.5 py-1.5 tabular-nums placeholder:font-normal placeholder:text-zinc-300 focus:outline-none focus:border-zinc-900 transition-colors">
                                </div>
                                <div class="flex justify-between items-center gap-2">
                                    <button id="btn-uang-pas" type="button"
                                        class="flex-1 text-xs font-semibold text-zinc-700 bg-white border border-zinc-200 hover:border-zinc-900 hover:text-zinc-900 rounded-lg py-1.5 tabular-nums transition-colors cursor-pointer">
                                        Uang pas
                                    </button>
                                    <span id="lbl-kembalian" class="font-bold tabular-nums text-xs text-right shrink-0">Rp 0</span>
                                </div>
                            </div>

                            {{-- QRIS --}}
                            <div id="row-qris" style="display:none">
                                <div class="bg-white border border-zinc-200 rounded-xl p-3 flex items-center gap-3">
                                    <img src="{{ asset('img/pay/qris-dummy.png') }}" alt="Kode QRIS"
                                        class="w-18 h-18 rounded-lg border border-zinc-200 [image-rendering:pixelated] grayscale contrast-125">
                                    <div class="min-w-0 flex-1">
                                        <img src="{{ asset('img/pay/qris.svg') }}" alt="QRIS" class="h-4 mb-1 grayscale">
                                        <p class="text-xs font-bold text-zinc-900">Scan QRIS</p>
                                        <p class="text-[11px] text-zinc-500 mt-0.5 leading-tight">Gopay, OVO, Dana, ShopeePay, BCA QR</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Transfer --}}
                            <div id="row-transfer" style="display:none" class="grid grid-cols-4 gap-1.5">
                                @foreach (['bca' => 'BCA', 'mandiri' => 'Mandiri', 'bri' => 'BRI', 'bni' => 'BNI'] as $kode => $nama)
                                    <label class="bank-opt bg-white border border-zinc-200 rounded-lg p-2 flex items-center justify-center cursor-pointer transition
                                                  hover:border-zinc-400 has-checked:border-zinc-900 has-checked:ring-1 has-checked:ring-zinc-900
                                                  has-checked:[&_img]:grayscale-0 has-checked:[&_img]:opacity-100">
                                        <input type="radio" name="bank_transfer" value="{{ $nama }}"
                                            class="hidden" {{ $kode === 'bca' ? 'checked' : '' }}>
                                        <img src="{{ asset("img/pay/{$kode}.svg") }}" alt="{{ $nama }}"
                                            class="h-5 grayscale opacity-70 transition-all">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="space-y-2 pt-0.5">
                        <button id="btn-preview-struk" type="button"
                            class="w-full border border-zinc-200 hover:border-zinc-900 hover:bg-zinc-100 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-2 text-xs transition-colors cursor-pointer">
                            Pratinjau Struk
                        </button>
                        <button id="btn-bayar" type="button"
                            class="w-full bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none text-white text-sm font-black rounded-xl py-3.5 tracking-wide tabular-nums transition cursor-pointer shadow-lg shadow-zinc-900/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-zinc-900/25">
                            Bayar
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- TOMBOL FLOATING KERANJANG (mobile) --}}
    <button id="btn-buka-cart" type="button" title="Buka keranjang" aria-label="Buka keranjang"
        class="lg:hidden fixed bottom-5 right-5 z-30 w-14 h-14 rounded-2xl bg-zinc-900 text-white shadow-2xl shadow-zinc-900/40 flex items-center justify-center active:scale-95 transition-all cursor-pointer">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
            <path d="M17 17h-11v-14h-2"/>
            <path d="M6 5l14 1l-1 7h-13"/>
        </svg>
        <span id="badge-cart-float" class="hidden absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center tabular-nums border-2 border-white"></span>
    </button>

    {{-- MODAL STRUK --}}
    <div id="modal-struk" role="dialog" aria-modal="true" aria-label="Struk pembelian" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-40 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 max-h-[90dvh] overflow-y-auto shadow-2xl">
            <div id="struk-body"></div>
            <div id="struk-actions" class="flex gap-2.5 mt-7">
                <button id="btn-print-struk" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Cetak Struk</button>
                <button id="btn-tutup-struk" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Transaksi Baru</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI KOSONGKAN --}}
    <div id="modal-konfirmasi-reset" role="dialog" aria-modal="true" aria-label="Konfirmasi kosongkan keranjang" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-40 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/><path d="M8 6v-2a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2l-1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Kosongkan Pesanan?</h3>
                <p class="text-sm text-zinc-600 mt-2"><span id="label-reset-count" class="font-bold text-zinc-900"></span> di keranjang akan dihapus. Tindakan ini tidak bisa dibatalkan.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-reset" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-reset" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Kosongkan</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS ITEM --}}
    <div id="modal-konfirmasi-hapus" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/><path d="M8 6v-2a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2l-1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Hapus Item Ini?</h3>
                <p class="text-sm text-zinc-600 mt-2"><span id="label-hapus-item" class="font-bold text-zinc-900"></span> akan dihapus dari pesanan.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-hapus" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-hapus" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI PINDAH GUDANG --}}
    <div id="modal-konfirmasi-gudang" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 9v4"/><path d="M12 17h.01"/><path d="M5 19h14a2 2 0 0 0 1.84-2.75L13.74 4.15a2 2 0 0 0-3.48 0L3.16 16.25A2 2 0 0 0 5 19z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Pindah Gudang Penyimpanan?</h3>
                <p class="text-sm text-zinc-600 mt-2">
                    Ada item di keranjang belanja. Jika Anda pindah ke <span id="target-nama-gudang" class="font-bold text-zinc-900">gudang ini</span>, pesanan saat ini akan dikosongkan.
                </p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-gudang" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-gudang" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Pindah Gudang</button>
            </div>
        </div>
    </div>

    {{-- MODAL PANDUAN SHORTCUT --}}
    <div id="modal-panduan-shortcut" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold tracking-tight">Shortcut</h3>
                <button id="btn-tutup-panduan-shortcut" type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 divide-y divide-zinc-100 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0">
                    <span class="text-sm text-zinc-600">Buka panduan ini</span>
                    <kbd class="kbd-shortcut">?</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Cari barang</span>
                    <kbd class="kbd-shortcut">F2</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Buka riwayat transaksi</span>
                    <kbd class="kbd-shortcut">F3</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Siklus metode pembayaran</span>
                    <kbd class="kbd-shortcut">F4</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Navigasi daftar barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">←</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">→</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↑</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Kurangi jumlah barang</span>
                    <kbd class="kbd-shortcut">-</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Tambah jumlah barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">+</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pilih barang keranjang terakhir</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pilih barang keranjang pertama</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">↑</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pindah antar barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">↑</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Hapus barang terpilih</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Del</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">Backspace</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Ganti satuan (barang terpilih)</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">R</kbd><span class="text-xs text-zinc-300 font-semibold">lalu</span><kbd class="kbd-shortcut">↑↓</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Bayar</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Uang pas</span>
                    <kbd class="kbd-shortcut">F7</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Rincian pembayaran</span>
                    <kbd class="kbd-shortcut">F6</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Muat ulang data & stok</span>
                    <kbd class="kbd-shortcut">F8</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Kosongkan pesanan</span>
                    <kbd class="kbd-shortcut">F9</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Tutup modal</span>
                    <kbd class="kbd-shortcut">Esc</kbd>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-zinc-100">
                <button id="btn-selesai-panduan-shortcut" type="button"
                    class="w-full bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white text-sm font-bold rounded-xl py-3 transition cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    {{-- MODAL RIWAYAT TRANSAKSI --}}
    <div id="modal-riwayat" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[85dvh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                <div>
                    <h3 class="text-sm font-bold tracking-tight">Riwayat Transaksi</h3>
                    <p class="text-[11px] text-zinc-500 mt-0.5" id="riwayat-tanggal-label">Transaksi hari ini</p>
                </div>
                <button id="btn-tutup-riwayat" type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="shrink-0 flex items-center gap-2 px-6 py-3 border-b border-zinc-100">
                <input id="riwayat-tanggal" type="date"
                    class="flex-1 border border-zinc-200 rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 bg-white focus:outline-none focus:border-zinc-900 transition-colors cursor-pointer tabular-nums">
                <button id="btn-riwayat-hari-ini" type="button"
                    class="shrink-0 px-4 py-2 rounded-lg border border-zinc-200 text-xs font-bold text-zinc-700 hover:border-zinc-900 hover:bg-zinc-50 transition-colors cursor-pointer">Hari Ini</button>
            </div>
            <div class="shrink-0 grid grid-cols-3 gap-2 px-6 py-2.5 border-b border-zinc-100">
                <label class="block min-w-0">
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wide block mb-1">Kasir</span>
                    <div id="filter-riwayat-kasir" class="relative">
                        <button type="button" data-dd-btn aria-haspopup="listbox" aria-expanded="false"
                            class="w-full flex items-center justify-between gap-1 border border-zinc-200 rounded-lg bg-white pl-2.5 pr-2 py-2 text-xs font-semibold text-zinc-700 hover:border-zinc-400 transition-colors cursor-pointer">
                            <span data-dd-value class="truncate min-w-0"></span>
                            <svg data-dd-chevron class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 6l4 4 4-4"/>
                            </svg>
                        </button>
                    </div>
                </label>
                <label class="block min-w-0">
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wide block mb-1">Gudang</span>
                    <div id="filter-riwayat-gudang" class="relative">
                        <button type="button" data-dd-btn aria-haspopup="listbox" aria-expanded="false"
                            class="w-full flex items-center justify-between gap-1 border border-zinc-200 rounded-lg bg-white pl-2.5 pr-2 py-2 text-xs font-semibold text-zinc-700 hover:border-zinc-400 transition-colors cursor-pointer">
                            <span data-dd-value class="truncate min-w-0"></span>
                            <svg data-dd-chevron class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 6l4 4 4-4"/>
                            </svg>
                        </button>
                    </div>
                </label>
                <label class="block min-w-0">
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wide block mb-1">Metode</span>
                    <div id="filter-riwayat-metode" class="relative">
                        <button type="button" data-dd-btn aria-haspopup="listbox" aria-expanded="false"
                            class="w-full flex items-center justify-between gap-1 border border-zinc-200 rounded-lg bg-white pl-2.5 pr-2 py-2 text-xs font-semibold text-zinc-700 hover:border-zinc-400 transition-colors cursor-pointer">
                            <span data-dd-value class="truncate min-w-0"></span>
                            <svg data-dd-chevron class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 6l4 4 4-4"/>
                            </svg>
                        </button>
                    </div>
                </label>
            </div>
            <div id="riwayat-summary" class="hidden shrink-0 flex items-center justify-between px-6 py-2.5 border-b border-zinc-100 text-xs">
                <span id="riwayat-summary-jumlah" class="font-bold text-zinc-600"></span>
                <span id="riwayat-summary-total" class="font-black text-zinc-900 tabular-nums"></span>
            </div>
            <div id="riwayat-pemuatan" class="px-6 py-12 text-center text-sm font-semibold text-zinc-500">Memuat riwayat…</div>
            <div id="riwayat-list" class="hidden flex-1 overflow-y-auto px-4 py-3 divide-y divide-zinc-100"></div>
            <div id="riwayat-kosong" class="hidden px-6 py-12 text-center">
                <svg class="w-10 h-10 text-zinc-200 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <path d="M8 8h8M8 12h8M8 16h5"></path>
                </svg>
                <p class="text-sm font-bold text-zinc-600">Belum ada transaksi pada tanggal ini</p>
            </div>
            <div class="px-4 py-3">
                <button id="btn-riwayat-lebih" type="button"
                    class="hidden w-full border border-zinc-200 hover:border-zinc-900 hover:bg-zinc-50 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-2.5 text-xs transition-all cursor-pointer">Muat lebih banyak</button>
            </div>
            <div class="px-6 py-4 border-t border-zinc-100">
                <button id="btn-tutup-riwayat-bawah" type="button"
                    class="w-full border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    {{-- MODAL OTORISASI PASSWORD CETAK ULANG STRUK --}}
    <div id="modal-password-cetak" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Otorisasi Cetak Ulang</h3>
                <p class="text-sm text-zinc-600 mt-1">Masukkan password otorisasi untuk mencetak ulang struk nota <span id="label-nota-password" class="font-bold text-zinc-900"></span>.</p>
            </div>
            <form id="form-password-cetak" class="mt-5 space-y-4">
                <div>
                    <input id="input-password-cetak" type="password" placeholder="Masukkan password" required autocomplete="off"
                        class="w-full h-11 border border-zinc-200 rounded-xl px-4 text-sm font-medium bg-white placeholder:text-zinc-500 focus:outline-none focus:border-zinc-900 transition-colors">
                    <p id="error-password-cetak" class="hidden text-xs font-semibold text-red-600 mt-1.5"></p>
                </div>
                <div class="flex gap-2.5">
                    <button id="btn-batal-password-cetak" type="button"
                        class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                    <button type="submit"
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Verifikasi</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL KONFIRMASI PEMBAYARAN NON-TUNAI --}}
    <div id="modal-konfirmasi-nontunai" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 11l1 2l2 -2"/>
                        <path d="M13 13l1 2l4 -4"/>
                        <path d="M7 17h10"/>
                        <path d="M12 19c-4.97 0 -9 -3.13 -9 -7s4.03 -7 9 -7s9 3.13 9 7"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Konfirmasi Pembayaran</h3>
                <p class="text-sm text-zinc-600 mt-2">Pembayaran <span id="label-metode-nontunai" class="font-bold text-zinc-900"></span> sebesar <span id="label-nominal-nontunai" class="font-black text-zinc-900 tabular-nums"></span>.</p>
                <p class="text-xs text-zinc-500 mt-2">Pastikan dana sudah diterima sebelum melanjutkan transaksi.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-nontunai" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-nontunai" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Terima Pembayaran</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI DISKON NOTA BESAR --}}
    <div id="modal-konfirmasi-diskon" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 9v4"/><path d="M12 17h.01"/><path d="M5 19h14a2 2 0 0 0 1.84-2.75L13.74 4.15a2 2 0 0 0-3.48 0L3.16 16.25A2 2 0 0 0 5 19z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Diskon Nota Besar</h3>
                <p class="text-sm text-zinc-600 mt-2">Diskon nota <span id="label-diskon-persen" class="font-bold text-zinc-900"></span> sebesar <span id="label-diskon-nominal" class="font-black text-zinc-900 tabular-nums"></span>. Lanjutkan transaksi?</p>
                <p class="text-xs text-zinc-500 mt-2">Pastikan diskon sudah disetujui atasan sebelum melanjutkan.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-diskon" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-diskon" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    <div id="toast" class="hidden"></div>
</body>
</html>
