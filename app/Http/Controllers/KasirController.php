<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\PromoBonus;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KasirController extends Controller
{
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
        $karyawanInfo = null;
        if ($karyawan = Auth::guard('karyawan')->user()) {
            $karyawanInfo = [
                'id' => $karyawan->id_karyawan,
                'nama' => $karyawan->nama_karyawan,
                'email' => $karyawan->email,
                'type' => 'karyawan',
            ];
        } elseif ($user = Auth::guard('web')->user()) {
            $karyawanInfo = [
                'id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'type' => 'user',
            ];
        }

        return view('kasir', [
            'kasirData' => [
                'karyawan' => $karyawanInfo,
                'barang' => Barang::bisaDijual()->with('gudangs')->get()->map(fn (Barang $b) => [
                    'id' => $b->id,
                    'jenis_barang_id' => $b->jenis_barang_id,
                    'nama_barang' => $b->nama_barang,
                    'gambar' => $b->gambar_url,
                    'tipe_barang' => $b->tipe_barang ?? 'barang_dagang',
                    'status' => $b->status ?? 'tersedia',
                    'butuh_proses' => (bool) $b->butuh_proses,
                    'nomer_seri' => $b->nomer_seri,
                    'barcode' => $b->barcode,
                    'harga_jual' => (int) $b->harga_jual,
                    'harga_beli' => (int) $b->harga_beli,
                    'tipe_harga_bertingkat' => $b->tipe_harga_bertingkat ?? 'persen',
                    'min_qty_1' => filled($b->min_qty_1) ? (int) $b->min_qty_1 : null,
                    'nilai_tier_1' => (float) ($b->nilai_tier_1 ?? 0),
                    'min_qty_2' => filled($b->min_qty_2) ? (int) $b->min_qty_2 : null,
                    'nilai_tier_2' => (float) ($b->nilai_tier_2 ?? 0),
                    'min_qty_3' => filled($b->min_qty_3) ? (int) $b->min_qty_3 : null,
                    'nilai_tier_3' => (float) ($b->nilai_tier_3 ?? 0),
                    'satuan' => $b->satuan ?? 'Pcs',
                    'units' => $b->getAvailableUnits(),
                    // stok per gudang dari pivot barang_gudang: { gudang_id: jumlah_dasar }
                    'stok' => $b->gudangs->mapWithKeys(fn ($g) => [$g->id => (int) $g->pivot->stok]),
                ]),
                'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
                'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
                'toko' => config('toko'),
                'kasirList' => array_merge(
                    Karyawan::all()->pluck('nama_karyawan')->all(),
                    User::all()->map(fn (User $u) => $u->name . ' [Admin]')->all(),
                ),
                'promoBonus' => PromoBonus::active()->get()->map(fn (PromoBonus $p) => [
                    'id' => $p->id,
                    'nama_promo' => $p->nama_promo,
                    'barang_utama_id' => $p->barang_utama_id,
                    'min_qty_utama' => (int) $p->min_qty_utama,
                    'satuan_utama' => $p->satuan_utama,
                    'barang_bonus_id' => $p->barang_bonus_id,
                    'qty_bonus' => (int) $p->qty_bonus,
                    'satuan_bonus' => $p->satuan_bonus,
                    'is_kelipatan' => (bool) $p->is_kelipatan,
                ]),
            ],
        ]);
    }

    /**
     * Endpoint API JSON untuk mengambil data produk & stok terbaru secara live.
     */
    public function data()
    {
        return response()->json([
            'barang' => Barang::bisaDijual()->with('gudangs')->get()->map(fn (Barang $b) => [
                'id' => $b->id,
                'jenis_barang_id' => $b->jenis_barang_id,
                'nama_barang' => $b->nama_barang,
                'gambar' => $b->gambar_url,
                'tipe_barang' => $b->tipe_barang ?? 'barang_dagang',
                'status' => $b->status ?? 'tersedia',
                'butuh_proses' => (bool) $b->butuh_proses,
                'nomer_seri' => $b->nomer_seri,
                'barcode' => $b->barcode,
                'harga_jual' => (int) $b->harga_jual,
                'harga_beli' => (int) $b->harga_beli,
                'tipe_harga_bertingkat' => $b->tipe_harga_bertingkat ?? 'persen',
                'min_qty_1' => filled($b->min_qty_1) ? (int) $b->min_qty_1 : null,
                'nilai_tier_1' => (float) ($b->nilai_tier_1 ?? 0),
                'min_qty_2' => filled($b->min_qty_2) ? (int) $b->min_qty_2 : null,
                'nilai_tier_2' => (float) ($b->nilai_tier_2 ?? 0),
                'min_qty_3' => filled($b->min_qty_3) ? (int) $b->min_qty_3 : null,
                'nilai_tier_3' => (float) ($b->nilai_tier_3 ?? 0),
                'satuan' => $b->satuan ?? 'Pcs',
                'units' => $b->getAvailableUnits(),
                'stok' => $b->gudangs->mapWithKeys(fn ($g) => [$g->id => (int) $g->pivot->stok]),
            ]),
            'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
            'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
            'toko' => config('toko'),
            'promoBonus' => PromoBonus::active()->get()->map(fn (PromoBonus $p) => [
                'id' => $p->id,
                'nama_promo' => $p->nama_promo,
                'barang_utama_id' => $p->barang_utama_id,
                'min_qty_utama' => (int) $p->min_qty_utama,
                'satuan_utama' => $p->satuan_utama,
                'barang_bonus_id' => $p->barang_bonus_id,
                'qty_bonus' => (int) $p->qty_bonus,
                'satuan_bonus' => $p->satuan_bonus,
                'is_kelipatan' => (bool) $p->is_kelipatan,
            ]),
        ])
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
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
                    $q2->whereHas('karyawan', fn ($qq) => $qq->where('nama_karyawan', $kasir))
                        ->orWhereHas('user', fn ($qq) => $qq->where('name', $kasirUser));
                });
            }
        };

        $penjualan = Penjualan::with(['details.barang', 'gudang', 'karyawan', 'user'])
            ->whereDate('tanggal', $tanggal)
            ->when($before !== null && (int) $before > 0, fn ($q) => $q->where('id', '<', (int) $before))
            ->where($scope)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $summary = Penjualan::whereDate('tanggal', $tanggal)
            ->where($scope)
            ->selectRaw('count(*) as jumlah, coalesce(sum(neto), 0) as total_neto')
            ->first();

        $items = $penjualan->map(fn (Penjualan $p) => $this->formatRiwayatItem($p));

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

        $penjualan = Penjualan::with(['details.barang', 'gudang', 'karyawan', 'user'])
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
            'jenis_pembayaran' => $p->jenis_pembayaran,
            'bayar' => (int) $p->bayar,
            'kembalian' => (int) $p->kembalian,
            'nama_kasir' => $p->nama_kasir,
            'gudang' => $p->gudang?->nama_gudang ?? '-',
            'jumlah_item' => (int) $p->details->sum('jumlah'),
            'details' => $p->details->map(fn (DetailJual $d) => [
                'barang_id' => (int) $d->barang_id,
                'nama_barang' => $d->barang?->nama_barang ?? '-',
                'jumlah' => (int) $d->jumlah,
                'satuan' => $d->satuan,
                'harga' => (int) $d->harga,
                'diskon' => (int) $d->diskon,
                'subtotal' => (int) $d->subtotal,
                'is_bonus' => (bool) $d->is_bonus,
                'promo_id' => $d->promo_id ? (int) $d->promo_id : null,
            ]),
        ];
    }

    /**
     * Simpan transaksi kasir.
     * Alurnya ngikutin pola CreatePembelian::afterCreate() punya admin Filament,
     * tapi arah stoknya keluar via StokService::kurangiStok().
     */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'diskon' => ['required', 'integer', 'min:0'],
            'diskon_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'jenis_pembayaran' => ['required', 'in:tunai,qris,transfer'],
            'bayar' => ['required', 'integer', 'min:0'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'details.*.jumlah' => ['required', 'integer', 'min:1'],
            'details.*.diskon' => ['required', 'integer', 'min:0'],
            'details.*.satuan' => ['nullable', 'string'],
            'details.*.is_bonus' => ['nullable', 'boolean'],
            'details.*.promo_id' => ['nullable', 'integer', 'exists:promo_bonus,id'],
        ]);

        $gudangId = $data['gudang_id'];

        // ===== Bulk-fetch semua Barang yang dibutuhkan dalam 1 query =====
        $allBarangIds = array_unique(array_column($data['details'], 'barang_id'));
        $barangs = Barang::whereIn('id', $allBarangIds)->get()->keyBy('id');

        foreach ($data['details'] as $d) {
            $item = $barangs->get($d['barang_id']);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan.',
                ], 422);
            }
            if ($item->status === 'habis') {
                return response()->json([
                    'success' => false,
                    'message' => "Menu '{$item->nama_barang}' sedang berstatus habis.",
                ], 422);
            }
            if (!$item->bisa_dijual) {
                return response()->json([
                    'success' => false,
                    'message' => "Barang '{$item->nama_barang}' tidak dapat dijual di kasir.",
                ], 422);
            }
        }

        // ===== Verifikasi bonus BELANJA dari aturan PromoBonus (jangan percaya browser) =====
        $bonusPool = [];
        $promoByMainBarang = [];
        $promos = PromoBonus::active()->get();
        if ($promos->isNotEmpty()) {
            // Eager-load relasi barangUtama & barangBonus agar tidak query lagi
            $promos->load('barangUtama', 'barangBonus');

            $mainQtyBase = [];
            foreach ($data['details'] as $d) {
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
        }

        $penjualan = DB::transaction(function () use ($data, $barangs, $gudangId, &$bonusPool, $promos, $promoByMainBarang) {
            // ===== Bulk-fetch semua stok sebelum loop (1 query) =====
            $stockRows = DB::table('barang_gudang')
                ->where('gudang_id', $gudangId)
                ->whereIn('barang_id', $barangs->keys()->all())
                ->get()
                ->keyBy('barang_id');
            $stockMap = $stockRows->mapWithKeys(fn ($row) => [$row->barang_id => (int) $row->stok]);

            // ===== Bulk-lock semua Barang yang dijual dalam 1 query =====
            $lockedBarangs = Barang::lockForUpdate()
                ->whereIn('id', $barangs->keys()->all())
                ->get()
                ->keyBy('id');

            // total & harga dihitung ulang di server (jangan percaya angka dari browser)
            $total = 0;
            $details = [];
            foreach ($data['details'] as $d) {
                $barang = $lockedBarangs->get($d['barang_id']);
                if (!$barang) {
                    abort(422, 'Barang tidak ditemukan');
                }
                $satuan = $d['satuan'] ?? $barang->satuan;
                $isBonus = !empty($d['is_bonus']);

                $faktor = $barang->getFaktorKonversi($satuan);
                $jumlahDasar = $d['jumlah'] * $faktor;

                if ($isBonus) {
                    // Bonus hanya sah kalau tercatat dalam "kumpulan bonus"
                    $poolKey = (int) $d['barang_id'];
                    $sisaBonus = $bonusPool[$poolKey]['base'] ?? 0;
                    if ($sisaBonus < $jumlahDasar) {
                        abort(422, "Item bonus {$barang->nama_barang} tidak sesuai aturan promo");
                    }
                    $bonusPool[$poolKey]['base'] = $sisaBonus - $jumlahDasar;

                    $hargaSatuan = 0;
                    $diskonItem = 0;
                    $subtotal = 0;
                    $hargaEfektif = 0;
                } else {
                    // Validasi stok dari data yang sudah di-fetch
                    $stokSekarang = $stockMap[$d['barang_id']] ?? 0;
                    if ($stokSekarang < $jumlahDasar) {
                        abort(422, "Stok {$barang->nama_barang} di gudang ini tidak cukup (tersedia: {$stokSekarang} {$barang->satuan})");
                    }

                    $hargaNormal = $barang->getHargaJualForSatuan($satuan);
                    $hargaTier = $barang->getHargaTierForQty((int) $d['jumlah'], $satuan);
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
                ];
            }

            if (count($details) === 0) {
                abort(422, 'Tidak ada item yang bisa dijual');
            }

            // diskon transaksi dihitung ulang dari persen agar tidak bisa dimanipulasi
            $diskonNominal = (int) floor($total * (int) ($data['diskon_persen'] ?? 0) / 100);
            $neto = max(0, $total - $diskonNominal);
            if ($data['jenis_pembayaran'] === 'tunai' && $data['bayar'] < $neto) {
                abort(422, 'Uang bayar kurang dari total');
            }
            $bayar = $data['jenis_pembayaran'] === 'tunai' ? $data['bayar'] : $neto;

            // nomer nota urut - PostgreSQL: FOR UPDATE harus di subquery, bukan di aggregate
            $countResult = DB::selectOne(
                'SELECT COUNT(*) as cnt FROM (SELECT id FROM penjualan WHERE tanggal::date = ? FOR UPDATE) as locked',
                [$data['tanggal']]
            );
            $urutan = ((int) ($countResult->cnt ?? 0)) + 1;
            $nomerNota = 'PJ-' . str_replace('-', '', $data['tanggal']) . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);

            $karyawanId = Auth::guard('karyawan')->check() ? Auth::guard('karyawan')->id() : null;
            $userId = Auth::guard('web')->check() ? Auth::guard('web')->id() : null;

            $penjualan = Penjualan::create([
                'nomer_nota' => $nomerNota,
                'karyawan_id' => $karyawanId,
                'user_id' => $userId,
                'gudang_id' => $gudangId,
                'tanggal' => $data['tanggal'],
                'total' => $total,
                'diskon' => $diskonNominal,
                'neto' => $neto,
                'jenis_pembayaran' => $data['jenis_pembayaran'],
                'bayar' => $bayar,
                'kembalian' => max(0, $bayar - $neto),
            ]);

            // ===== Bulk-insert DetailJual (1 query alih-alih N) =====
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
                    'penjualan_id' => $penjualan->id,
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
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('detail_jual')->insert($detailRows);

            // ===== Kurangi stok via StokService (perlu lockForUpdate per-barang) =====
            $stokService = app(StokService::class);
            foreach ($details as $d) {
                $stokService->kurangiStok(
                    barangId: $d['barang']->id,
                    gudangId: $gudangId,
                    jumlah: $d['jumlah_dasar'],
                    konteks: [
                        'nomer_entry' => $nomerNota,
                        'tanggal' => $data['tanggal'],
                        'harga' => $d['harga_efektif'],
                        'keterangan' => "Penjualan kasir ({$d['jumlah']} {$d['satuan']})",
                        'jenis' => KartuStok::JENIS_KELUAR,
                        'nomer_seri' => $d['barang']->nomer_seri ?? null,
                    ],
                    validasi: false,
                );
            }

            return $penjualan;
        });

        return response()->json($penjualan->load('details.barang'));
    }
}
