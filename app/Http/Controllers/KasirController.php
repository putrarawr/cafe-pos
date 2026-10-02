<?php

namespace App\Http\Controllers;

use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\BarangHargaAplikator;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\Karyawan;
use App\Models\OrderPending;
use App\Models\Penjualan;
use App\Models\PromoBonus;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KasirController extends Controller
{
    /**
     * Namespace advisory lock PostgreSQL untuk pembuatan nomor order pending.
     * Dipasangkan dengan crc32(prefix tanggal) jadi kunci per hari.
     */
    private const KUNCI_ADVISORY_ORDER_PENDING = 8821;

    /** @var array<int, array<int, int>>|null [barang_id][gudang_id] => stok fisik, di-cache per request */
    private ?array $cacheStokFisik = null;

    /** @var array<int, array<int, int>>|null [barang_id][gudang_id] => jumlah_dasar ter-reserve */
    private ?array $cacheReservasi = null;

    /** @var array<int, int>|null daftar id gudang */
    private ?array $cacheGudangIds = null;

    /**
     * Tampilkan halaman login kasir.
     */
    public function showLogin()
    {
        if (Auth::guard('karyawan')->check() || Auth::guard('web')->check()) {
            return redirect()->route('kasir');
        }

        return view('kasir-login');
    }

    /**
     * Proses login kasir.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 1. Coba login sebagai Karyawan
        if (Auth::guard('karyawan')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('kasir'));
        }

        // 2. Jika gagal, coba login sebagai Admin (User)
        $loginField = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        if (Auth::guard('web')->attempt([$loginField => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('kasir'));
        }

        return back()->withErrors([
            'email' => 'Email/username atau password salah.',
        ])->onlyInput('email');
    }

    /**
     * Logout kasir.
     */
    public function logout(Request $request)
    {
        Auth::guard('karyawan')->logout();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('kasir.login');
    }

    /**
     * Halaman kasir. Data langsung disuntik ke Blade (tanpa API),
     * nanti dibaca JavaScript lewat window.KASIR_DATA.
     */
    public function index()
    {
        return view('kasir', [
            'kasirData' => [
                'karyawan' => $this->getKaryawanInfo(),
                'barang' => $this->getBarangData(),
                'kemasanDefault' => $this->getKemasanDefaultData(),
                'barangKemasan' => $this->getBarangKemasanData(),
                'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
                'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
                'aplikator' => $this->getAplikatorData(),
                'toko' => config('toko'),
                'kasirList' => $this->getKasirListData(),
                'promoBonus' => $this->getPromoBonusData(),
            ],
        ]);
    }

    /**
     * Endpoint API JSON untuk mengambil data produk & stok terbaru secara live.
     */
    public function data()
    {
        return response()->json([
            'barang' => $this->getBarangData(),
            'kemasanDefault' => $this->getKemasanDefaultData(),
            'barangKemasan' => $this->getBarangKemasanData(),
            'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
            'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
            'aplikator' => $this->getAplikatorData(),
            'toko' => config('toko'),
            'promoBonus' => $this->getPromoBonusData(),
        ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Ambil data master barang yang dapat dijual untuk kasir.
     */
    private function getBarangData(): array
    {
        return Barang::bisaDijual()
            ->where(function ($q) {
                $q->whereNull('tipe_barang')
                    ->orWhereNotIn('tipe_barang', ['kemasan', 'barang_pembantu']);
            })
            // gudangs dipakai buat stok & min stok per gudang, kemasan & hargaAplikators
            // buat kartu produk. Tanpa ini jadi N+1 di setiap request kasir.
            ->with(['gudangs', 'kemasan', 'hargaAplikators'])
            ->get()
            ->map(fn(Barang $b) => [
                'id' => $b->id,
                'jenis_barang_id' => $b->jenis_barang_id,
                'nama_barang' => $b->nama_barang,
                'gambar' => $b->gambar_url,
                'tipe_barang' => $b->tipe_barang ?? 'barang_dagang',
                'status' => $b->status ?? 'tersedia',
                'butuh_proses' => (bool) $b->butuh_proses,
                'kemasan_id' => $b->kemasan_id,
                'kemasan' => $b->kemasan ? [
                    'id' => $b->kemasan->id,
                    'nama_barang' => $b->kemasan->nama_barang,
                    'harga_jual' => (int) $b->kemasan->harga_jual,
                    'satuan' => $b->kemasan->satuan ?? 'Pcs',
                ] : null,
                'nomer_seri' => $b->nomer_seri,
                'barcode' => $b->barcode,
                'harga_jual' => (int) $b->harga_jual,
                'harga_beli' => (int) $b->harga_beli,
                'harga_aplikator' => $b->hargaAplikators->mapWithKeys(fn($h) => [(int) $h->aplikator_id => (int) $h->harga_jual]),
                'tipe_harga_bertingkat' => $b->tipe_harga_bertingkat ?? 'persen',
                'min_qty_1' => filled($b->min_qty_1) ? (int) $b->min_qty_1 : null,
                'nilai_tier_1' => (float) ($b->nilai_tier_1 ?? 0),
                'min_qty_2' => filled($b->min_qty_2) ? (int) $b->min_qty_2 : null,
                'nilai_tier_2' => (float) ($b->nilai_tier_2 ?? 0),
                'min_qty_3' => filled($b->min_qty_3) ? (int) $b->min_qty_3 : null,
                'nilai_tier_3' => (float) ($b->nilai_tier_3 ?? 0),
                'satuan' => $b->satuan ?? 'Pcs',
                'units' => $b->getAvailableUnits(),
                ...$this->petaStok($b->id),
                'stok_minimum' => (int) ($b->stok_minimum ?? ($b->tipe_barang === 'barang_jadi' ? 0 : 20)),
                'stok_minimum_gudang' => $b->gudangs->mapWithKeys(fn($g) => [
                    $g->id => (int) ($g->pivot->stok_minimum ?? $b->stok_minimum ?? ($b->tipe_barang === 'barang_jadi' ? 0 : 20))
                ]),
            ])
            ->all();
    }

    /**
     * Stok fisik per barang per gudang: [barang_id][gudang_id] => stok.
     */
    private function stokFisikPerGudang(): array
    {
        if ($this->cacheStokFisik !== null) {
            return $this->cacheStokFisik;
        }

        $result = [];
        foreach (DB::table('barang_gudang')->get(['barang_id', 'gudang_id', 'stok']) as $row) {
            $result[(int) $row->barang_id][(int) $row->gudang_id] = (int) $row->stok;
        }

        return $this->cacheStokFisik = $result;
    }

    /**
     * Qty ter-reserve order pending per barang per gudang: [barang_id][gudang_id] => jumlah_dasar.
     */
    private function reservasiPerGudang(): array
    {
        if ($this->cacheReservasi !== null) {
            return $this->cacheReservasi;
        }

        $gudangIds = $this->gudangIds();
        if (empty($gudangIds)) {
            return $this->cacheReservasi = [];
        }

        $rows = DB::table('order_pending_item as item')
            ->join('order_pending as o', 'o.id', '=', 'item.order_pending_id')
            ->where('o.status', OrderPending::STATUS_PENDING)
            ->whereIn('item.gudang_id', $gudangIds)
            ->groupBy('item.barang_id', 'item.gudang_id')
            ->select('item.barang_id', 'item.gudang_id', DB::raw('SUM(item.jumlah_dasar) as qty'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->barang_id][(int) $row->gudang_id] = (int) $row->qty;
        }

        return $this->cacheReservasi = $result;
    }

    /**
     * @return array<int, int>
     */
    private function gudangIds(): array
    {
        return $this->cacheGudangIds ??= Gudang::pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /**
     * Tiga peta stok untuk satu barang: fisik, ter-reserve, dan tersedia.
     */
    private function petaStok(int $barangId): array
    {
        $stokPerGudang = $this->stokFisikPerGudang();
        $reservasiPerGudang = $this->reservasiPerGudang();

        $fisik = [];
        $reservasi = [];
        $tersedia = [];

        foreach ($this->gudangIds() as $gudangId) {
            $sisa = (int) ($stokPerGudang[$barangId][$gudangId] ?? 0);
            $tahan = (int) ($reservasiPerGudang[$barangId][$gudangId] ?? 0);
            $fisik[$gudangId] = $sisa;
            $reservasi[$gudangId] = $tahan;
            $tersedia[$gudangId] = max(0, $sisa - $tahan);
        }

        return [
            'stok' => $fisik,
            'stok_reservasi' => $reservasi,
            'stok_tersedia' => $tersedia,
        ];
    }

    /**
     * Ambil data barang kemasan/pembantu untuk kasir.
     */
    private function getBarangKemasanData(): array
    {
        return Barang::whereIn('tipe_barang', ['kemasan', 'barang_pembantu'])
            ->where('status', 'tersedia')
            ->get()
            ->map(fn(Barang $b) => [
                'id' => $b->id,
                'nama_barang' => $b->nama_barang,
                'harga_jual' => (int) $b->harga_jual,
                'satuan' => $b->satuan ?? 'Pcs',
                ...$this->petaStok($b->id),
            ])
            ->all();
    }

    /**
     * Ambil data promo bonus aktif untuk kasir.
     */
    private function getPromoBonusData(): array
    {
        return PromoBonus::active()->get()->map(fn(PromoBonus $p) => [
            'id' => $p->id,
            'nama_promo' => $p->nama_promo,
            'barang_utama_id' => $p->barang_utama_id,
            'min_qty_utama' => (int) $p->min_qty_utama,
            'satuan_utama' => $p->satuan_utama,
            'barang_bonus_id' => $p->barang_bonus_id,
            'qty_bonus' => (int) $p->qty_bonus,
            'satuan_bonus' => $p->satuan_bonus,
            'is_kelipatan' => (bool) $p->is_kelipatan,
        ])->all();
    }

    /**
     * Ambil info login karyawan atau user web yang sedang aktif.
     */
    private function getKaryawanInfo(): ?array
    {
        if ($karyawan = Auth::guard('karyawan')->user()) {
            return [
                'id' => $karyawan->id_karyawan,
                'nama' => $karyawan->nama_karyawan,
                'email' => $karyawan->email,
                'type' => 'karyawan',
            ];
        }

        if ($user = Auth::guard('web')->user()) {
            return [
                'id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'type' => 'user',
            ];
        }

        return null;
    }

    /**
     * Ambil daftar nama kasir dan admin.
     */
    private function getKasirListData(): array
    {
        return array_merge(
            Karyawan::all()->pluck('nama_karyawan')->all(),
            User::all()->map(fn(User $u) => $u->name . ' [Admin]')->all(),
        );
    }

    /**
     * Ambil data platform aplikator delivery aktif untuk kasir beserta logo publik.
     */
    private function getAplikatorData(): array
    {
        return Aplikator::aktif()
            ->orderBy('nama_aplikator')
            ->get()
            ->map(fn(Aplikator $a) => [
                'id' => $a->id,
                'nama_aplikator' => $a->nama_aplikator,
                'kode_aplikator' => $a->kode_aplikator,
                'persentase_komisi' => (float) $a->persentase_komisi,
                'gambar' => $a->gambar_url,
            ])
            ->all();
    }

    /**
     * Ambil data barang kemasan default yang aktif untuk kasir.
     */
    private function getKemasanDefaultData(): ?array
    {
        $kemasan = Barang::kemasanDefault()->with('gudangs')->first();
        if (!$kemasan) {
            $kemasan = Barang::whereIn('tipe_barang', ['kemasan', 'barang_pembantu'])
                ->where('status', 'tersedia')
                ->with('gudangs')
                ->first();
        }

        if (!$kemasan) {
            return null;
        }

        return [
            'id' => $kemasan->id,
            'nama_barang' => $kemasan->nama_barang,
            'harga_jual' => (int) $kemasan->harga_jual,
            'satuan' => $kemasan->satuan ?? 'Pcs',
            ...$this->petaStok($kemasan->id),
        ];
    }

    /**
     * Riwayat transaksi (default: hari ini). Dipakai halaman kasir
     * untuk melihat nota & cetak ulang struk.
     */
    public function riwayat(Request $request)
    {
        $tanggal = $request->query('tanggal', now()->toDateString());
        $limit = max(1, min((int) $request->query('limit', 50), 200));
        $before = $request->query('before');
        $kasir = trim((string) $request->query('kasir', ''));
        $gudangId = $request->query('gudang_id');
        $metode = $request->query('metode');

        if ($gudangId !== null && (int) $gudangId <= 0) {
            $gudangId = null;
        }
        if (!in_array($metode, ['tunai', 'qris', 'transfer'], true)) {
            $metode = null;
        }
        $kasirUser = $kasir !== '' ? preg_replace('/\s*\[Admin\]\s*$/i', '', $kasir) : '';

        $scope = function ($q) use ($kasir, $kasirUser, $gudangId, $metode) {
            if ($gudangId) {
                $q->where('gudang_id', (int) $gudangId);
            }
            if ($metode) {
                $q->where('jenis_pembayaran', $metode);
            }
            if ($kasir !== '') {
                $q->where(function ($q2) use ($kasir, $kasirUser) {
                    $q2->whereHas('karyawan', fn($qq) => $qq->where('nama_karyawan', $kasir))
                        ->orWhereHas('user', fn($qq) => $qq->where('name', $kasirUser));
                });
            }
        };

        $penjualan = Penjualan::with(['details.barang', 'gudang', 'karyawan', 'user', 'aplikator'])
            ->whereDate('tanggal', $tanggal)
            ->when($before !== null && (int) $before > 0, fn($q) => $q->where('id', '<', (int) $before))
            ->where($scope)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $summary = Penjualan::whereDate('tanggal', $tanggal)
            ->where($scope)
            ->selectRaw('count(*) as jumlah, coalesce(sum(neto), 0) as total_neto')
            ->first();

        $items = $penjualan->map(fn(Penjualan $p) => $this->formatRiwayatItem($p));

        return response()->json([
            'items' => $items,
            'summary' => [
                'jumlah' => (int) ($summary->jumlah ?? 0),
                'total_neto' => (int) ($summary->total_neto ?? 0),
            ],
        ]);
    }

    /**
     * Otorisasi cetak ulang struk lewat password user yang sedang login.
     * Dipanggil saat kasir menekan tombol "Cetak" di riwayat transaksi.
     */
    public function verifikasiCetak(Request $request, int $id)
    {
        $password = trim((string) $request->input('password', ''));
        $authUser = Auth::guard('karyawan')->user() ?? Auth::guard('web')->user();

        if (!$authUser || $password === '' || !Hash::check($password, $authUser->password)) {
            return response()->json(['message' => 'Password salah! Silakan coba lagi.'], 422);
        }

        $penjualan = Penjualan::with(['details.barang', 'gudang', 'karyawan', 'user', 'aplikator'])
            ->find($id);

        if (!$penjualan) {
            return response()->json(['message' => 'Nota tidak ditemukan'], 404);
        }

        return response()->json($this->formatRiwayatItem($penjualan));
    }

    /**
     * Bentuk standar satu item riwayat transaksi (dipakai list & cetak ulang).
     */
    private function formatRiwayatItem(Penjualan $p): array
    {
        return [
            'id' => $p->id,
            'nomer_nota' => $p->nomer_nota,
            'tanggal' => (string) $p->tanggal,
            'jam' => $p->created_at?->isSameDay($p->tanggal)
                ? $p->created_at->format('H:i')
                : '-',
            'total' => (int) $p->total,
            'diskon' => (int) $p->diskon,
            'diskon_persen' => (int) $p->diskon > 0 && (int) $p->diskon + (int) $p->neto > 0
                ? (int) round(($p->diskon / ((int) $p->diskon + (int) $p->neto)) * 100)
                : 0,
            'neto' => (int) $p->neto,
            'alamat_pengiriman' => $p->alamat_pengiriman ?? null,
            'biaya_kirim' => (int) $p->biaya_kirim,
            'aplikator_id' => $p->aplikator_id ? (int) $p->aplikator_id : null,
            'aplikator' => $p->aplikator?->nama_aplikator ?? null,
            'jenis_pembayaran' => $p->jenis_pembayaran,
            'bayar' => (int) $p->bayar,
            'kembalian' => (int) $p->kembalian,
            'nama_kasir' => $p->nama_kasir,
            'gudang' => $p->gudang?->nama_gudang ?? '-',
            'jumlah_item' => (int) $p->details->sum('jumlah'),
            'details' => $p->details->map(fn(DetailJual $d) => [
                'barang_id' => (int) $d->barang_id,
                'nama_barang' => $d->barang?->nama_barang ?? '-',
                'jumlah' => (int) $d->jumlah,
                'satuan' => $d->satuan,
                'harga' => (int) $d->harga,
                'diskon' => (int) $d->diskon,
                'subtotal' => (int) $d->subtotal,
                'is_bonus' => (bool) $d->is_bonus,
                'promo_id' => $d->promo_id ? (int) $d->promo_id : null,
                'jenis_pesanan' => $d->jenis_pesanan ?? 'dine_in',
            ]),
        ];
    }

    /**
     * Aturan validasi keranjang kasir, dipakai bareng oleh simpan() dan
     * simpanOrderPending() supaya keduanya tidak bisa berbeda aturan.
     *
     * Yang SENGAJA tidak divalidasi di sini: total, neto, kembalian, harga,
     * subtotal, nama_barang, dan gudang_id per item — semuanya dihitung ulang
     * di server supaya tidak bisa dimanipulasi dari browser.
     */
    private function aturanValidasiKeranjang(): array
    {
        return [
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'diskon' => ['required', 'integer', 'min:0'],
            'diskon_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'jenis_pembayaran' => ['required', 'in:tunai,qris,transfer'],
            'bayar' => ['required', 'integer', 'min:0'],
            'alamat_pengiriman' => ['nullable', 'string'],
            'biaya_kirim' => ['nullable', 'integer', 'min:0'],
            'aplikator_id' => ['nullable', 'integer', 'exists:aplikator,id'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'details.*.jumlah' => ['required', 'integer', 'min:1'],
            'details.*.diskon' => ['required', 'integer', 'min:0'],
            'details.*.satuan' => ['nullable', 'string'],
            'details.*.is_bonus' => ['nullable', 'boolean'],
            'details.*.promo_id' => ['nullable', 'integer', 'exists:promo_bonus,id'],
            'details.*.jenis_pesanan' => ['nullable', 'in:dine_in,take_away,delivery'],
            // opsional: order pending yang sedang diselesaikan
            'order_pending_id' => ['nullable', 'integer', 'exists:order_pending,id'],
        ];
    }

    /**
     * Guard eligibility barang (ada / tidak habis / boleh dijual).
     * Dipakai bareng oleh simpan() dan simpanOrderPending().
     */
    private function cekKetersediaanBarang(array $details, Collection $barangs): void
    {
        foreach ($details as $d) {
            $item = $barangs->get($d['barang_id']);
            if (!$item) {
                $this->tolak('Barang tidak ditemukan.');
            }
            if ($item->status === 'habis') {
                $this->tolak("Menu '{$item->nama_barang}' sedang berstatus habis.");
            }
            if (!$item->bisa_dijual && !in_array($item->tipe_barang, ['kemasan', 'barang_pembantu'])) {
                $this->tolak("Barang '{$item->nama_barang}' tidak dapat dijual di kasir.");
            }
        }
    }

    /**
     * Tolak request kasir dengan JSON 422.
     *
     * Dipakai sebagai ganti abort(422) supaya bentuk respons tetap sama dengan
     * yang sudah consuming frontend & test lama: { success: false, message }.
     */
    private function tolak(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
        ], 422));
    }

    /**
     * Verifikasi bonus BELANJA dari aturan PromoBonus (jangan percaya browser).
     * Dipakai bareng oleh simpan() dan simpanOrderPending().
     *
     * @return array{0: array, 1: Collection, 2: array} [bonusPool, promos, promoByMainBarang]
     */
    private function verifikasiPromoBonus(array $details, Collection $barangs): array
    {
        $bonusPool = [];
        $promoByMainBarang = [];
        $promos = PromoBonus::active()->get();

        if ($promos->isEmpty()) {
            return [$bonusPool, $promos, $promoByMainBarang];
        }

        // Eager-load relasi barangUtama & barangBonus agar tidak query lagi
        $promos->load('barangUtama', 'barangBonus');

        $mainQtyBase = [];
        foreach ($details as $d) {
            if (!empty($d['is_bonus'])) {
                continue;
            }
            $mainBarang = $barangs->get($d['barang_id']);
            if (!$mainBarang) {
                continue;
            }
            $faktor = $mainBarang->getFaktorKonversi($d['satuan'] ?? $mainBarang->satuan);
            $mainQtyBase[$d['barang_id']] = ($mainQtyBase[$d['barang_id']] ?? 0) + ((int) $d['jumlah'] * $faktor);
        }

        foreach ($promos as $promo) {
            $mainBarang = $promo->barangUtama;
            $bonusBarang = $promo->barangBonus;
            if (!$mainBarang || !$bonusBarang) {
                continue;
            }
            $faktorUtama = $mainBarang->getFaktorKonversi($promo->satuan_utama);
            $minBase = (int) $promo->min_qty_utama * $faktorUtama;
            $totalMain = $mainQtyBase[$promo->barang_utama_id] ?? 0;
            if ($totalMain < $minBase) {
                continue;
            }
            $multiplier = $promo->is_kelipatan ? (int) floor($totalMain / $minBase) : 1;
            $bonusQty = (int) $promo->qty_bonus * max(1, $multiplier);
            if ($bonusQty < 1) {
                continue;
            }
            $faktorBonus = $bonusBarang->getFaktorKonversi($promo->satuan_bonus);
            $key = (int) $promo->barang_bonus_id;
            $bonusPool[$key]['base'] = ($bonusPool[$key]['base'] ?? 0) + ($bonusQty * $faktorBonus);

            $promoByMainBarang[$promo->barang_utama_id] = [
                'promo_id' => $promo->id,
                'bonus_barang_id' => $promo->barang_bonus_id,
                'bonus_qty' => $bonusQty,
                'bonus_satuan' => $promo->satuan_bonus ?? $bonusBarang->satuan,
                'bonus_hpp' => $bonusBarang->getHppForSatuan($promo->satuan_bonus ?? $bonusBarang->satuan),
            ];
        }

        return [$bonusPool, $promos, $promoByMainBarang];
    }

    /**
     * Simpan transaksi kasir.
     * Alurnya ngikutin pola CreatePembelian::afterCreate() punya admin Filament,
     * tapi arah stoknya keluar via StokService::kurangiStok().
     */
    public function simpan(Request $request)
    {
        $data = $request->validate($this->aturanValidasiKeranjang());

        // Order pending yang sedang diselesaikan: reservasi stoknya sendiri tidak
        // boleh mengurangi jatah, makanya dikecualikan dari perhitungan reservasi.
        $orderPending = null;
        if (!empty($data['order_pending_id'])) {
            $orderPending = OrderPending::where('status', OrderPending::STATUS_PENDING)
                ->find((int) $data['order_pending_id']);
            if (!$orderPending) {
                $this->tolak('Order pending tidak ditemukan atau sudah tidak aktif.');
            }
            if ((int) $orderPending->gudang_id !== (int) $data['gudang_id']) {
                $this->tolak('Order pending ini dari gudang lain.');
            }
        }

        $gudangId = $data['gudang_id'];

        // ===== Pilihan aplikator WAJIB bila ada item delivery =====
        $hasDelivery = collect($data['details'])->contains(
            fn($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'delivery'
        );
        if ($hasDelivery && empty($data['aplikator_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Wajib pilih aplikator delivery (GoFood/GrabFood/ShopeeFood) sebelum menyimpan.',
            ], 422);
        }

        // Ongkir & alamat hanya diterapkan bila ada item antar (delivery/take_away),
        // selaras guard aplikator_id di atas agar ongkir tidak bocor ke transaksi non-antar.
        $hasAntar = $hasDelivery || collect($data['details'])->contains(
            fn($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'take_away'
        );
        $biayaKirimFinal = $hasAntar ? (int) ($data['biaya_kirim'] ?? 0) : 0;
        $alamatPengirimanFinal = $hasAntar ? ($data['alamat_pengiriman'] ?? null) : null;

        // ===== Bulk-fetch semua Barang yang dibutuhkan dalam 1 query =====
        $allBarangIds = array_unique(array_column($data['details'], 'barang_id'));
        $barangs = Barang::whereIn('id', $allBarangIds)->get()->keyBy('id');

        $this->cekKetersediaanBarang($data['details'], $barangs);

        // ===== Verifikasi bonus BELANJA dari aturan PromoBonus =====
        [$bonusPool, $promos, $promoByMainBarang] = $this->verifikasiPromoBonus($data['details'], $barangs);

        $penjualan = DB::transaction(fn () => $this->simpanTransaksiPenjualan(
            $data,
            $barangs,
            $gudangId,
            $hasDelivery,
            $biayaKirimFinal,
            $alamatPengirimanFinal,
            $bonusPool,
            $promos,
            $promoByMainBarang,
            $orderPending?->id,
        ));

        return response()->json($penjualan->load('details.barang'));
    }

    // =====================================================================
    // ORDER PENDING
    //
    // Order pending = keranjang kasir yang "ditahan" (belum dibayar).
    // Stok fisiknya TIDAK berkurang; yang di-reserve cuma "hak pakai" lewat
    // tabel order_pending_item. Stok tersedia = stok fisik - total reservasi.
    // =====================================================================

    /**
     * Simpan keranjang kasir saat ini sebagai order pending.
     *
     * Stok divalidasi (fisik - reservasi order lain) di dalam transaksi yang
     * mengunci baris stok, jadi dua kasir tidak bisa memilih barang yang sama
     * pada saat berdekatan.
     */
    public function simpanOrderPending(Request $request)
    {
        $data = $request->validate($this->aturanValidasiKeranjang());

        $gudangId = (int) $data['gudang_id'];

        $hasDelivery = collect($data['details'])->contains(
            fn($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'delivery'
        );
        if ($hasDelivery && empty($data['aplikator_id'])) {
            $this->tolak('Wajib pilih aplikator delivery (GoFood/GrabFood/ShopeeFood) sebelum menyimpan order.');
        }

        $hasAntar = $hasDelivery || collect($data['details'])->contains(
            fn($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'take_away'
        );
        $biayaKirimFinal = $hasAntar ? (int) ($data['biaya_kirim'] ?? 0) : 0;
        $alamatPengirimanFinal = $hasAntar ? ($data['alamat_pengiriman'] ?? null) : null;

        $allBarangIds = array_unique(array_column($data['details'], 'barang_id'));
        $barangs = Barang::whereIn('id', $allBarangIds)->get()->keyBy('id');

        $this->cekKetersediaanBarang($data['details'], $barangs);

        // Harga ikut dihitung server (sama seperti transaksi) supaya snapshot
        // di daftar order bukan sumber kebenaran, jangan percaya browser.
        [$bonusPool, $promos, $promoByMainBarang] = $this->verifikasiPromoBonus($data['details'], $barangs);

        $order = DB::transaction(fn () => $this->buatOrderPending(
            $data,
            $barangs,
            $gudangId,
            $hasDelivery,
            $biayaKirimFinal,
            $alamatPengirimanFinal,
            $bonusPool,
            $promos,
            $promoByMainBarang
        ));

        return response()->json($order->load('items.barang'), 201);
    }

    /**
     * Daftar order pending, terbaru dulu. Dipakai panel order di halaman kasir.
     */
    public function daftarOrderPending(Request $request)
    {
        $gudangId = $request->query('gudang_id');
        $limit = max(1, min((int) $request->query('limit', 50), 200));

        $query = OrderPending::query()
            ->where('status', OrderPending::STATUS_PENDING)
            ->with(['gudang', 'karyawan', 'user', 'items.barang'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($gudangId !== null && (int) $gudangId > 0) {
            $query->where('gudang_id', (int) $gudangId);
        }

        $orders = $query->limit($limit)->get();

        return response()->json([
            'items' => $orders->map(fn(OrderPending $o) => [
                'id' => $o->id,
                'kode_order' => $o->kode_order,
                'gudang_id' => (int) $o->gudang_id,
                'nama_gudang' => $o->gudang?->nama_gudang ?? '-',
                'nama_kasir' => $o->nama_kasir,
                'total' => (int) $o->total,
                'diskon' => (int) $o->diskon,
                'neto' => (int) $o->neto,
                'jumlah_item' => (int) $o->items->sum('jumlah'),
                'jumlah_baris' => $o->items->count(),
                'created_at' => $o->created_at?->toIso8601String(),
                'items' => $o->items->map(fn($it) => [
                    'barang_id' => (int) $it->barang_id,
                    'nama_barang' => $it->barang?->nama_barang ?? '-',
                    'jumlah' => (int) $it->jumlah,
                    'satuan' => $it->satuan,
                    'jenis_pesanan' => $it->jenis_pesanan,
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * Detail satu order pending, untuk memuat ulang isinya ke keranjang kasir.
     *
     * Harga di response sengaja TIDAK dikembalikan sebagai sumber kebenaran: frontend
     * cukup pakai barang_id + satuan + jumlah + jenis_pesanan, lalu harga dihitung
     * ulang dari master barang. Snapshot harga cuma dipakai buat daftar.
     */
    public function detailOrderPending($id)
    {
        $order = OrderPending::with(['gudang', 'aplikator', 'items.barang'])
            ->find($id);

        if (!$order) {
            abort(404, 'Order pending tidak ditemukan.');
        }

        // Cegah kasir melanjutkan order yang barusan dibatalkan/dibayar kasir lain.
        if ($order->status !== OrderPending::STATUS_PENDING) {
            abort(409, 'Order ini sudah tidak aktif, silakan muat ulang daftarnya.');
        }

        return response()->json([
            'id' => $order->id,
            'kode_order' => $order->kode_order,
            'status' => $order->status,
            'gudang_id' => (int) $order->gudang_id,
            'nama_gudang' => $order->gudang?->nama_gudang ?? '-',
            'tanggal' => $order->tanggal?->toDateString(),
            'diskon_persen' => (int) $order->diskon_persen,
            'total' => (int) $order->total,
            'diskon' => (int) $order->diskon,
            'neto' => (int) $order->neto,
            'biaya_kirim' => (int) $order->biaya_kirim,
            'alamat_pengiriman' => $order->alamat_pengiriman,
            'aplikator_id' => $order->aplikator_id ? (int) $order->aplikator_id : null,
            'catatan' => $order->catatan,
            'items' => $order->items->map(fn($it) => [
                'barang_id' => (int) $it->barang_id,
                'satuan' => $it->satuan,
                'jumlah' => (int) $it->jumlah,
                'jumlah_dasar' => (int) $it->jumlah_dasar,
                'is_bonus' => (bool) $it->is_bonus,
                'promo_id' => $it->promo_id ? (int) $it->promo_id : null,
                'jenis_pesanan' => $it->jenis_pesanan,
            ])->all(),
        ]);
    }

    /**
     * Batalkan order pending → reservasi stok dilepas.
     */
    public function batalkanOrderPending($id)
    {
        $order = DB::transaction(function () use ($id) {
            // lockForUpdate supaya pembatalan berhadapan langsung dengan pembayaran:
            // kalau kasir lain sedang memproses bayar order ini, kita tunggu sampai
            // statusnya selesai lalu jawab "sudah tidak aktif" (bukan dilepas diam-diam).
            $order = OrderPending::whereKey($id)->lockForUpdate()->first();

            if (!$order || $order->status !== OrderPending::STATUS_PENDING) {
                return null;
            }

            $order->update([
                'status' => OrderPending::STATUS_BATAL,
                'updated_at' => now(),
            ]);

            return $order;
        });

        if (!$order) {
            abort(404, 'Order pending tidak ditemukan atau sudah tidak aktif.');
        }

        return response()->json([
            'success' => true,
            'message' => "Order {$order->kode_order} dibatalkan, stok sudah dilepas.",
        ]);
    }

    /**
     * Buat header + item order pending di dalam transaksi yang mengunci stok.
     */
    private function buatOrderPending(
        array $data,
        Collection $barangs,
        int $gudangId,
        bool $hasDelivery,
        int $biayaKirimFinal,
        ?string $alamatPengirimanFinal,
        array &$bonusPool,
        Collection $promos,
        array $promoByMainBarang
    ): OrderPending {
        // Hitung harga, tier, dan cek bonus promo persis seperti transaksi.
        [$total, $details] = $this->kalkulasiItemPenjualan(
            $data['details'],
            $barangs,
            $gudangId,
            $hasDelivery,
            !empty($data['aplikator_id']) ? (int) $data['aplikator_id'] : null,
            $bonusPool
        );

        $diskonPersen = (int) ($data['diskon_persen'] ?? 0);
        $diskonNominal = (int) floor($total * $diskonPersen / 100);
        $neto = max(0, $total - $diskonNominal);

        $order = OrderPending::create([
            'kode_order' => $this->kodeOrderBaru($data['tanggal']),
            'gudang_id' => $gudangId,
            'status' => OrderPending::STATUS_PENDING,
            'karyawan_id' => Auth::guard('karyawan')->check() ? Auth::guard('karyawan')->id() : null,
            'user_id' => Auth::guard('web')->check() ? Auth::guard('web')->id() : null,
            'tanggal' => $data['tanggal'],
            'diskon_persen' => $diskonPersen,
            'total' => $total,
            'diskon' => $diskonNominal,
            'neto' => $neto + $biayaKirimFinal,
            'biaya_kirim' => $biayaKirimFinal,
            'alamat_pengiriman' => $alamatPengirimanFinal,
            'aplikator_id' => $hasDelivery && !empty($data['aplikator_id']) ? (int) $data['aplikator_id'] : null,
        ]);

        $rows = [];
        foreach ($details as $d) {
            $rows[] = [
                'order_pending_id' => $order->id,
                'barang_id' => $d['barang']->id,
                'gudang_id' => $gudangId,
                'satuan' => $d['satuan'],
                'jumlah' => $d['jumlah'],
                // ini yang di-reserve: satuan dasar, bukan qty sesuai satuan pilihan
                'jumlah_dasar' => $d['jumlah_dasar'],
                'harga' => $d['harga'],
                'diskon' => $d['diskon'],
                'subtotal' => $d['subtotal'],
                'is_bonus' => $d['is_bonus'],
                'promo_id' => $promoByMainBarang[$d['barang']->id]['promo_id'] ?? ($d['promo_id'] ?? null),
                'jenis_pesanan' => $d['jenis_pesanan'] ?? 'dine_in',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('order_pending_item')->insert($rows);

        return $order->fresh();
    }

    /**
     * Nomor order pending: ORD-YYYYMMDD-0001.
     *
     * Sengaja terpisah dari nomor nota penjualan supaya order pending tidak
     * menggeser nomor nota transaksi yang sudah jadi.
     */
    private function kodeOrderBaru(string $tanggal): string
    {
        $prefix = 'ORD-' . str_replace('-', '', $tanggal) . '-';

        // lockForUpdate() di bawah TIDAK mengunci apa-apa kalau order pertama hari itu
        // belum ada (nol baris = nol lock), jadi dua kasir bisa dapat angka yang sama.
        // Advisory lock transaksional menutup celah itu: kuncinya ikut tanggal, jadi
        // antrean hanya terjadi untuk order tanggal yang sama, dan otomatis lepas
        // begitu transaksi selesai atau rollback.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_xact_lock(?, ?)', [
                self::KUNCI_ADVISORY_ORDER_PENDING,
                crc32($prefix),
            ]);
        }

        $suffix = DB::table('order_pending')
            ->where('kode_order', 'like', $prefix . '%')
            ->orderByDesc('kode_order')
            ->lockForUpdate()
            ->value('kode_order');

        $urutan = 1;
        if ($suffix) {
            $urutan = ((int) substr($suffix, strlen($prefix))) + 1;
        }

        return $prefix . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Eksekusi transaksi database untuk mencatat penjualan, detail item, dan mutasi stok.
     */
    private function simpanTransaksiPenjualan(
        array $data,
        Collection $barangs,
        int $gudangId,
        bool $hasDelivery,
        int $biayaKirimFinal,
        ?string $alamatPengirimanFinal,
        array &$bonusPool,
        Collection $promos,
        array $promoByMainBarang,
        ?int $orderPendingId = null
    ): Penjualan {
        $aplikatorId = !empty($data['aplikator_id']) ? (int) $data['aplikator_id'] : null;

        // Kunci baris order pending DI DALAM transaksi, sebelum stok dihitung.
        // Pembatalan juga mengunci baris yang sama, jadi bayar-vs-batal tidak
        // bisa saling lolos: salah satu pasti menunggu lalu lihat status terbaru.
        if ($orderPendingId) {
            $orderTerkunci = OrderPending::whereKey($orderPendingId)
                ->lockForUpdate()
                ->first();

            if (!$orderTerkunci || $orderTerkunci->status !== OrderPending::STATUS_PENDING) {
                $this->tolak('Order pending sudah dibatalkan atau diselesaikan kasir lain.');
            }
            if ((int) $orderTerkunci->gudang_id !== $gudangId) {
                $this->tolak('Order pending ini dari gudang lain.');
            }
        }

        [$total, $details] = $this->kalkulasiItemPenjualan(
            $data['details'],
            $barangs,
            $gudangId,
            $hasDelivery,
            $aplikatorId,
            $bonusPool,
            $orderPendingId
        );

        $penjualan = $this->buatPenjualan(
            $data,
            $gudangId,
            $total,
            $biayaKirimFinal,
            $alamatPengirimanFinal,
            $hasDelivery,
            $aplikatorId
        );

        $this->simpanDetailJual($penjualan->id, $gudangId, $details, $promos, $promoByMainBarang);

        $this->potongStokPenjualan($penjualan->nomer_nota, $data['tanggal'], $gudangId, $details);

        // Order pending selesai: reservasi otomatis "lepas" karena yang dihitung
        // sebagai reservasi cuma order berstatus pending.
        if ($orderPendingId) {
            OrderPending::whereKey($orderPendingId)->update([
                'status' => OrderPending::STATUS_SELESAI,
                'penjualan_id' => $penjualan->id,
                'updated_at' => now(),
            ]);
        }

        return $penjualan;
    }

    /**
     * Kalkulasi harga, potongan tier, validasi stok, dan pembentukan struktur detail item penjualan.
     *
     * @return array{0: int, 1: array}
     */
    private function kalkulasiItemPenjualan(
        array $detailsInput,
        Collection $barangs,
        int $gudangId,
        bool $hasDelivery,
        ?int $aplikatorId,
        array &$bonusPool,
        ?int $orderPendingId = null
    ): array {
        // WAJIB lock dulu, baru baca stok. Kalau dibalik (baca -> lock),
        // dua kasir bisa sama-sama baca stok 5, dua-duanya lolos cek,
        // lalu berurutan mengikis stok jadi 3.
        // Baris barang_gudang yang belum ada tidak bisa di-lock, jadi kunci
        // master barang dulu (pasti ada) lalu kunci baris stoknya.
        $lockedBarangs = Barang::lockForUpdate()
            ->whereIn('id', $barangs->keys()->all())
            ->get()
            ->keyBy('id');

        $stockRows = DB::table('barang_gudang')
            ->where('gudang_id', $gudangId)
            ->whereIn('barang_id', $barangs->keys()->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('barang_id');

        $stokFisikMap = $stockRows->mapWithKeys(fn($row) => [$row->barang_id => (int) $row->stok]);

        // Stok yang bisa dipakai = stok fisik - qty yang di-reserve order pending
        // milik order lain. Order yang sedang diselesaikan dikecualikan karena
        // jatahnya memang milik dia sendiri.
        $reservasiMap = app(StokService::class)->reservasiAktif($gudangId, $orderPendingId);
        $stockMap = $stokFisikMap->map(fn($stok, $barangId) => $stok - (int) ($reservasiMap[$barangId] ?? 0));

        $hargaAplikatorMap = [];
        if ($hasDelivery && $aplikatorId) {
            $hargaAplikatorMap = BarangHargaAplikator::where('aplikator_id', $aplikatorId)
                ->whereIn('barang_id', $barangs->keys()->all())
                ->get()
                ->pluck('harga_jual', 'barang_id')
                ->map(fn($h) => (int) $h)
                ->all();
        }

        $total = 0;
        $details = [];
        foreach ($detailsInput as $d) {
            $barang = $lockedBarangs->get($d['barang_id']);
            if (!$barang) {
                $this->tolak('Barang tidak ditemukan');
            }
            $satuan = $d['satuan'] ?? $barang->satuan;
            $isBonus = !empty($d['is_bonus']);

            $faktor = $barang->getFaktorKonversi($satuan);
            $jumlahDasar = $d['jumlah'] * $faktor;

            if ($isBonus) {
                $poolKey = (int) $d['barang_id'];
                $sisaBonus = $bonusPool[$poolKey]['base'] ?? 0;
                if ($sisaBonus < $jumlahDasar) {
                    $this->tolak("Item bonus {$barang->nama_barang} tidak sesuai aturan promo");
                }
                $bonusPool[$poolKey]['base'] = $sisaBonus - $jumlahDasar;

                $hargaSatuan = 0;
                $diskonItem = 0;
                $subtotal = 0;
                $hargaEfektif = 0;
            } else {
                $stokSekarang = $stockMap[$d['barang_id']] ?? 0;
                if ($stokSekarang < $jumlahDasar) {
                    $terpakaiOrder = (int) ($reservasiMap[$d['barang_id']] ?? 0);
                    $keteranganReservasi = $terpakaiOrder > 0
                        ? " (sudah ada $terpakaiOrder {$barang->satuan} dipesan di order lain)"
                        : '';

                    $this->tolak("Stok {$barang->nama_barang} di gudang ini tidak cukup "
                        . "(tersedia: {$stokSekarang} {$barang->satuan}{$keteranganReservasi})");
                }

                $isDelivery = ($d['jenis_pesanan'] ?? 'dine_in') === 'delivery';
                $hargaAplikatorSatuan = $isDelivery ? ($hargaAplikatorMap[$d['barang_id']] ?? null) : null;
                if ($hargaAplikatorSatuan !== null) {
                    $hargaNormal = $hargaAplikatorSatuan * $faktor;
                    $hargaTier = $barang->getHargaTierForQty((int) $d['jumlah'], $satuan, $hargaNormal);
                } else {
                    $hargaNormal = $barang->getHargaJualForSatuan($satuan);
                    $hargaTier = $barang->getHargaTierForQty((int) $d['jumlah'], $satuan);
                }
                $potonganTier = max(0, ($hargaNormal - $hargaTier) * (int) $d['jumlah']);
                $diskonItem = $potonganTier;
                $hargaSatuan = $hargaNormal;
                $hargaEfektif = $hargaTier;
                $subtotal = ($hargaNormal * (int) $d['jumlah']) - $diskonItem;
            }

            $total += $subtotal;
            $hppSatuan = $barang->getHppForSatuan($satuan);
            $details[] = [
                'barang' => $barang,
                'jumlah' => $d['jumlah'],
                'jumlah_dasar' => $jumlahDasar,
                'harga' => $hargaSatuan,
                'harga_efektif' => $hargaEfektif,
                'hpp' => $hppSatuan,
                'diskon' => $diskonItem,
                'subtotal' => $subtotal,
                'satuan' => $satuan,
                'is_bonus' => $isBonus,
                'promo_id' => $d['promo_id'] ?? null,
                'jenis_pesanan' => $d['jenis_pesanan'] ?? 'dine_in',
            ];
        }

        if (count($details) === 0) {
            abort(422, 'Tidak ada item yang bisa dijual');
        }

        return [$total, $details];
    }

    /**
     * Buat data header transaksi Penjualan.
     */
    private function buatPenjualan(
        array $data,
        int $gudangId,
        int $total,
        int $biayaKirimFinal,
        ?string $alamatPengirimanFinal,
        bool $hasDelivery,
        ?int $aplikatorId
    ): Penjualan {
        $diskonNominal = (int) floor($total * (int) ($data['diskon_persen'] ?? 0) / 100);
        $neto = max(0, $total - $diskonNominal);
        $netoWithKirim = $neto + $biayaKirimFinal;

        if ($data['jenis_pembayaran'] === 'tunai' && $data['bayar'] < $netoWithKirim) {
            abort(422, 'Uang bayar kurang dari total');
        }
        $bayarFinal = $data['jenis_pembayaran'] === 'tunai' ? $data['bayar'] : $netoWithKirim;

        $countResult = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM (SELECT id FROM penjualan WHERE tanggal::date = ? FOR UPDATE) as locked',
            [$data['tanggal']]
        );
        $urutan = ((int) ($countResult->cnt ?? 0)) + 1;
        $nomerNota = 'PJ-' . str_replace('-', '', $data['tanggal']) . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);

        $karyawanId = Auth::guard('karyawan')->check() ? Auth::guard('karyawan')->id() : null;
        $userId = Auth::guard('web')->check() ? Auth::guard('web')->id() : null;

        $komisiPersen = 0;
        if ($hasDelivery && $aplikatorId) {
            $aplikator = Aplikator::find($aplikatorId);
            $komisiPersen = $aplikator ? (float) $aplikator->persentase_komisi : 0;
        }

        $komisiAplikator = $hasDelivery && $aplikatorId && $komisiPersen > 0
            ? (int) round($neto * $komisiPersen / 100)
            : null;

        return Penjualan::create([
            'nomer_nota' => $nomerNota,
            'karyawan_id' => $karyawanId,
            'user_id' => $userId,
            'gudang_id' => $gudangId,
            'tanggal' => $data['tanggal'],
            'total' => $total,
            'diskon' => $diskonNominal,
            'neto' => $netoWithKirim,
            'jenis_pembayaran' => $data['jenis_pembayaran'],
            'bayar' => $bayarFinal,
            'kembalian' => max(0, $bayarFinal - $netoWithKirim),
            'alamat_pengiriman' => $alamatPengirimanFinal,
            'biaya_kirim' => $biayaKirimFinal,
            'aplikator_id' => $hasDelivery ? $aplikatorId : null,
            'komisi_aplikator' => $komisiAplikator,
        ]);
    }

    /**
     * Simpan seluruh baris detail penjualan beserta info promo bonus ke tabel detail_jual.
     */
    private function simpanDetailJual(
        int $penjualanId,
        int $gudangId,
        array $details,
        Collection $promos,
        array $promoByMainBarang
    ): void {
        $detailRows = [];
        foreach ($details as $d) {
            $bonusBarangId = null;
            $bonusQty = null;
            $bonusSatuan = null;
            $bonusHpp = null;
            $promoId = $d['promo_id'] ?? null;

            if (!$d['is_bonus']) {
                if (isset($promoByMainBarang[$d['barang']->id])) {
                    $pInfo = $promoByMainBarang[$d['barang']->id];
                    $promoId = $pInfo['promo_id'];
                    $bonusBarangId = $pInfo['bonus_barang_id'];
                    $bonusQty = $pInfo['bonus_qty'];
                    $bonusSatuan = $pInfo['bonus_satuan'];
                    $bonusHpp = $pInfo['bonus_hpp'];
                } elseif (!empty($d['promo_id']) && $promos->isNotEmpty()) {
                    $promo = $promos->firstWhere('id', $d['promo_id']);
                    if ($promo && $promo->barangBonus) {
                        $bonusBarangId = $promo->barang_bonus_id;
                        $faktorUtama = $d['barang']->getFaktorKonversi($promo->satuan_utama);
                        $minBase = (int) $promo->min_qty_utama * $faktorUtama;
                        $multiplier = ($promo->is_kelipatan && $minBase > 0) ? (int) floor($d['jumlah_dasar'] / $minBase) : 1;
                        $bonusQty = (int) $promo->qty_bonus * max(1, $multiplier);
                        $bonusSatuan = $promo->satuan_bonus ?? $promo->barangBonus->satuan;
                        $bonusHpp = $promo->barangBonus->getHppForSatuan($bonusSatuan);
                    }
                }
            }

            $detailRows[] = [
                'penjualan_id' => $penjualanId,
                'barang_id' => $d['barang']->id,
                'gudang_id' => $gudangId,
                'satuan' => $d['satuan'],
                'jumlah' => $d['jumlah'],
                'harga' => $d['harga'],
                'hpp' => $d['hpp'],
                'diskon' => $d['diskon'],
                'subtotal' => $d['subtotal'],
                'is_bonus' => $d['is_bonus'],
                'promo_id' => $promoId,
                'bonus_barang_id' => $bonusBarangId,
                'bonus_qty' => $bonusQty,
                'bonus_satuan' => $bonusSatuan,
                'bonus_hpp' => $bonusHpp,
                'jenis_pesanan' => $d['jenis_pesanan'] ?? 'dine_in',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('detail_jual')->insert($detailRows);
    }

    /**
     * Mutasi pengurangan stok barang gudang dan pencatatan kartu stok penjualan.
     */
    private function potongStokPenjualan(string $nomerNota, string $tanggal, int $gudangId, array $details): void
    {
        $stokService = app(StokService::class);
        foreach ($details as $d) {
            $stokService->kurangiStok(
                barangId: $d['barang']->id,
                gudangId: $gudangId,
                jumlah: $d['jumlah_dasar'],
                konteks: [
                    'nomer_entry' => $nomerNota,
                    'tanggal' => $tanggal,
                    'harga' => $d['harga_efektif'],
                    'keterangan' => "Penjualan kasir ({$d['jumlah']} {$d['satuan']})",
                    'jenis' => KartuStok::JENIS_KELUAR,
                    'nomer_seri' => $d['barang']->nomer_seri ?? null,
                ],
                validasi: false,
            );
        }
    }
}
