<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen POS & Inventaris</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000;
            margin: 0;
            padding: 0;
        }
        
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #000;
        }
        ::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .shadow-overlap {
            box-shadow: 0 -20px 50px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body class="antialiased text-white">

    <!-- Section 1: Hero (Sticky - Overlapped by Section 2) -->
    <section class="sticky top-0 h-screen bg-black text-white flex flex-col justify-center items-center px-6 text-center z-10">
        <h1 class="text-5xl md:text-7xl font-extrabold tracking-tighter mb-6 uppercase leading-tight">
            Sistem Terpadu <br/> 
            <span class="text-neutral-500">POS &</span> Inventaris
        </h1>
        <p class="text-lg md:text-2xl text-neutral-400 max-w-2xl font-light leading-relaxed">
            Solusi operasional untuk memanajemen penjualan kasir dan mengontrol stok barang gudang dalam satu ekosistem presisi.
        </p>
        
        <div class="absolute bottom-12 animate-bounce opacity-50 flex flex-col items-center">
            <span class="text-xs uppercase tracking-widest mb-2 font-semibold">Jelajahi Fitur</span>
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
            </svg>
        </div>
    </section>

    <!-- Section 2: Modul Visual (Sticky - Akan menutupi Section 1 dan tertutupi Section 3) -->
    <section class="sticky top-0 h-screen z-20 bg-neutral-200 text-black flex flex-col justify-center items-center px-6 shadow-overlap overflow-hidden">
        <div class="max-w-6xl w-full flex flex-col lg:flex-row items-center justify-center gap-12 relative">
            <div class="lg:w-1/2 text-center lg:text-left z-10">
                <span class="uppercase tracking-widest text-neutral-500 font-bold text-sm mb-4 block">Eksplorasi Modul</span>
                <h2 class="text-4xl md:text-6xl font-bold tracking-tight mb-6 text-black uppercase">Dua Modul, <br/>Satu Ekosistem.</h2>
                <p class="text-lg text-neutral-600 leading-relaxed font-light mb-8 max-w-lg mx-auto lg:mx-0">
                    Data transaksi kasir otomatis memotong kuantitas stok di sistem gudang. Tidak ada lagi sinkronisasi manual yang memakan waktu. Semua berjalan secara instan dan real-time.
                </p>
            </div>
            
            <div class="lg:w-1/2 flex justify-center z-10 gap-6">
                <!-- Modul Kasir Box -->
                <div class="w-48 h-64 border-4 border-black p-4 flex flex-col gap-4 bg-white transform -rotate-6 shadow-xl hover:rotate-0 transition-transform duration-300">
                    <div class="text-sm font-bold uppercase border-b-2 border-black pb-2">Modul Kasir</div>
                    <div class="w-full h-8 bg-black mt-2"></div>
                    <div class="flex flex-col gap-2 mt-auto">
                        <div class="w-full h-3 bg-neutral-300"></div>
                        <div class="w-3/4 h-3 bg-neutral-300"></div>
                    </div>
                </div>
                <!-- Modul Gudang Box -->
                <div class="w-48 h-64 border-4 border-black p-4 flex flex-col gap-4 bg-black text-white transform rotate-6 shadow-xl hover:rotate-0 transition-transform duration-300 mt-12">
                    <div class="text-sm font-bold uppercase border-b-2 border-white pb-2 text-white">Modul Gudang</div>
                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <div class="w-full h-10 border-2 border-white bg-neutral-800"></div>
                        <div class="w-full h-10 border-2 border-white bg-neutral-800"></div>
                        <div class="w-full h-10 border-2 border-white bg-neutral-800"></div>
                        <div class="w-full h-10 border-2 border-white bg-neutral-800"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- Section 3: Navigasi Login (Normal Scroll - Akan menutupi Section 2) -->
    <section class="relative z-30 bg-black text-white h-screen flex flex-col justify-center items-center px-6 text-center shadow-overlap">
        <span class="uppercase tracking-widest text-neutral-500 font-bold text-sm mb-4 block">Autentikasi Pengguna</span>
        <h2 class="text-4xl md:text-6xl font-bold tracking-tight mb-16 uppercase">Pilih Portal Akses</h2>
        
        <div class="flex flex-col sm:flex-row gap-6 w-full max-w-3xl">
            <!-- Akses Kasir -->
            <a href="{{ route('kasir.login') }}" class="flex-1 group relative block h-56 md:h-72 border-2 border-neutral-800 bg-neutral-950 hover:bg-white hover:border-white transition-all duration-300 overflow-hidden flex flex-col justify-center items-center cursor-pointer">
                <div class="relative z-10 text-center transition-colors duration-300 group-hover:text-black">
                    <svg class="w-16 h-16 mx-auto mb-6 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="block text-2xl font-bold uppercase tracking-widest">Kasir</span>
                    <span class="block text-sm font-light mt-2 opacity-70 group-hover:opacity-100 group-hover:font-medium">Masuk untuk transaksi penjualan</span>
                </div>
                <div class="absolute inset-0 bg-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-500 ease-out z-0"></div>
            </a>
            
            <!-- Akses Gudang -->
            <a href="/admin/login" class="flex-1 group relative block h-56 md:h-72 border-2 border-neutral-800 bg-neutral-950 hover:bg-white hover:border-white transition-all duration-300 overflow-hidden flex flex-col justify-center items-center cursor-pointer">
                <div class="relative z-10 text-center transition-colors duration-300 group-hover:text-black">
                    <svg class="w-16 h-16 mx-auto mb-6 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="block text-2xl font-bold uppercase tracking-widest">Gudang</span>
                    <span class="block text-sm font-light mt-2 opacity-70 group-hover:opacity-100 group-hover:font-medium">Masuk untuk manajemen stok</span>
                </div>
                <div class="absolute inset-0 bg-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-500 ease-out z-0"></div>
            </a>
        </div>
        
        <div class="mt-24 text-neutral-600 text-sm tracking-wide">
            &copy; {{ date('Y') }} Sistem Terpadu POS & Inventaris. Hak Cipta Dilindungi.
        </div>
    </section>

</body>
</html>
