# Spesifikasi Desain: Status Meja, Durasi, & Dashboard Pilih Meja (Kasir)

## 1. Ringkasan Fitur

Kasir memilih meja untuk pesanan **dine in** lewat dashboard grid meja yang muncul otomatis saat menekan tombol **Bayar** atau **Order**. Setiap meja punya status visual (tersedia / terisi / overstay), dan meja yang sedang dipakai tidak bisa dipilih dua kali.

Komponen utama:

- **Master data `meja`** — dikelola dari Filament (`/admin/mejas`), discope per gudang.
- **Sesi meja `sesi_meja`** — mencatat linimasa pemakaian satu meja (mulai → selesai, durasi, overstay).
- **Overlay dashboard meja** di halaman kasir — grid kartu berwarna, countdown durasi, tombol lepas.
- **Durasi per meja** (`durasi_menit`, default 60) untuk menandai meja yang sudah lewat batas.

### Keputusan desain yang sudah disepakati

| Keputusan | Nilai |
|---|---|
| Kapan meja bebas | **Otomatis setelah dibayar** |
| Bentuk dashboard | **Overlay besar di halaman kasir** |
| Cakupan durasi | **Per meja, default 60 menit** |
| Meja yang terisi | **Satu sesi aktif; wajib dilepas dulu** untuk sesi berikutnya |
| Order ditahan (pending) | **Meja ikut terpegang** selama order pending |

> **Catatan trade-off.** Karena meja otomatis bebas setelah dibayar, fitur durasi terutama berguna untuk mendeteksi **overstay pada order yang masih pending** (pelanggan sudah pesan tapi belum bayar / belum pergi). Untuk kasus "sudah bayar tapi masih lama duduk", tersedia jalur keluar manual lewat tombol **Lepas Meja**, dan lihat catatan Fase 2 di bagian 10.

## 2. Alur Kerja Kasir

```
Buka kasir → tambah produk → keranjang punya baris Dine in
   │
   ├─ klik "BAYAR"  ─┐
   └─ klik "ORDER"  ─┴─►  OVERLAY DASHBOARD MEJA (buka otomatis)
                            │  🟢 Hijau  tersedia        → bisa diklik
                            │  🔴 Merah  terisi           → ditolak, tampil info order
                            │  ⚫ OVER nn′ lewat durasi   → ditolak, badge merah tua
                            │  ⚪ Abu    tidak aktif      → ditolak (maintenance)
                            ▼
                       Meja dipilih → lanjut ke pembayaran / tahan pesanan
                            ▼
                   ORDER DITAHAN ──► sesi meja = terisi ──► 🔴 terkunci + badge sisa waktu
                            │
                   BAYAR / CANCEL ──► sesi selesai/batal ──► 🟢 bebas lagi
```

Overlay **tidak pernah** muncul bila keranjang tidak punya item `dine_in` — take away dan delivery tidak pernah butuh meja.

## 3. Skema Database & Migrasi

### 3.1 Tabel `meja` (master)

```php
Schema::create('meja', function (Blueprint $table) {
    $table->id();
    $table->string('kode_meja', 50);
    $table->string('nama_meja', 100);
    $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
    $table->string('area')->nullable();
    $table->unsignedSmallInteger('kapasitas')->default(4);
    $table->unsignedSmallInteger('durasi_menit')->default(60);
    $table->boolean('status_aktif')->default(true);
    $table->timestamps();

    $table->unique(['gudang_id', 'kode_meja'], 'meja_gudang_kode_unique');
    $table->index(['gudang_id', 'status_aktif'], 'meja_gudang_status_index');
});
```

`gudang_id` dipakai karena halaman kasir sudah scoped per gudang (`#dd-gudang`) — meja cabang lain tidak boleh selectable.

Penamaan meja disederhanakan jadi `Meja 01`, `Meja 02`, ... dan diulang di tiap cabang, jadi `kode_meja` hanya unik per gudang (`2026_10_06_090000_make_kode_meja_unik_per_gudang.php`). `nama_meja` biasanya diisi area (Indoor/Teras/VIP) dan UI tidak menampilkannya kalau isinya sama dengan kode.

### 3.2 Tabel `sesi_meja` (pemakaian meja)

Satu baris = satu periode pemakaian satu meja. Hanya `status = 'terisi'` yang mengunci meja.

```php
Schema::create('sesi_meja', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meja_id')->constrained('meja')->cascadeOnDelete();
    $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
    $table->foreignId('karyawan_id')->nullable()->constrained('karyawan', 'id_karyawan')->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->enum('status', ['terisi', 'selesai', 'batal'])->default('terisi');
    $table->dateTime('mulai')->index();
    $table->dateTime('selesai')->nullable();
    $table->unsignedSmallInteger('batas_menit')->default(60);   // snapshot meja.durasi_menit
    $table->unsignedInteger('durasi_menit_terpakai')->default(0);
    $table->boolean('overstay')->default(false);
    $table->foreignId('order_pending_id')->nullable()->constrained('order_pending')->nullOnDelete();
    $table->foreignId('penjualan_id')->nullable()->constrained('penjualan')->nullOnDelete();
    $table->timestamps();

    $table->index(['meja_id', 'status'], 'sesi_meja_meja_status_index');
});

// Meja yang sudah terisi TIDAK BISA di-order lagi — ditegakkan database.
DB::statement(
    "CREATE UNIQUE INDEX sesi_meja_aktif_unik ON sesi_meja (meja_id) WHERE status = 'terisi'"
);
```

`batas_menit` sengaja di-snapshot: kalau admin mengubah `meja.durasi_menit` di tengah sesi, sesi yang sedang berjalan tidak ikut berubah.

### 3.3 Tambah kolom ke `penjualan` & `order_pending`

- `penjualan.meja_id` — FK nullable `nullOnDelete`
- `penjualan.durasi_meja_menit` — unsignedInteger nullable, snapshot total menit dari `sesi_meja.mulai` sampai pembayaran
- `order_pending.meja_id` — FK nullable `nullOnDelete`

> **Tidak ada baris draf di `penjualan`.** Nomor nota dihitung dari `COUNT(*)` penjualan per tanggal (`KasirController::simpanTransaksiPenjualan`), jadi baris draf akan menggeser seluruh nomor nota. Semua status meja lewat `sesi_meja` — alasan yang sama membuat `order_pending` dipisah.

## 4. Eloquent Model

### `app/Models/Meja.php` (baru)

`$guarded = ['id']` · casts `kapasitas`, `durasi_menit`, `status_aktif` · `scopeAktif()` · relasi `gudang()`, `sesi()` hasMany, `sesiAktif()` hasOne scoped `status = 'terisi'` · `LogsActivity` dengan `logOnly([...])` seperti `Gudang`.

### `app/Models/SesiMeja.php` (baru)

Const `STATUS_TERISI`, `STATUS_SELESAI`, `STATUS_BATAL` · casts `mulai`/`selesai` datetime · `scopeAktif()` · relasi `meja()`, `gudang()`, `karyawan()`, `user()`, `orderPending()`, `penjualan()` · method:

- `lamaMenit(): int` — selisih `mulai` → `selesai ?? now()`
- `sisaMenit(): int` — `max(0, batas_menit - lamaMenit())`
- `sudahOver(): bool` — sesi aktif dan `sisaMenit() === 0`

### Model yang diperbarui

| File | Perubahan |
|---|---|
| `app/Models/Penjualan.php` | + `meja_id`, `durasi_meja_menit` di `$fillable`; + `meja()` belongsTo; + `sesiMeja()` hasOne via `penjualan_id` |
| `app/Models/OrderPending.php` | + `meja_id` di `$fillable`; + `meja()` belongsTo; + `sesiMeja()` hasOne via `order_pending_id` |

## 5. Backend Kasir

### 5.1 Route (`routes/web.php`, di dalam `auth:karyawan,web`)

```php
Route::get ('/kasir/meja',             [KasirController::class, 'daftarMeja'])->name('kasir.meja.index');
Route::post('/kasir/meja/{id}/lepas', [KasirController::class, 'lepasMeja'])->name('kasir.meja.lepas');
```

### 5.2 `daftarMeja()`

Satu kueri: `Meja` + `leftJoin` sesi `terisi` + order pending + penjualan. Mengembalikan per meja:

```php
[
    'id', 'kode_meja', 'nama_meja', 'area', 'kapasitas', 'durasi_menit',
    'status'          => 'tersedia'|'terisi'|'overstay',
    'sesi_id', 'mulai', 'lama_menit', 'sisa_menit', 'overstay',
    'order_pending_id', 'kode_order', 'total', 'nama_kasir',
]
```

Filter `?gudang_id=` opsional, sama seperti `daftarOrderPending()`.

### 5.3 Validasi

`aturanValidasiKeranjang()` menambah:

```php
'meja_id' => ['nullable', 'integer', 'exists:meja,id'],
```

### 5.4 Guard di `simpan()` dan `simpanOrderPending()`

Pola sama dengan guard aplikator:

```php
$hasDineIn = collect($data['details'])
    ->contains(fn ($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'dine_in');

if ($hasDineIn && empty($data['meja_id'])) {
    return $this->tolak('Pilih meja dulu untuk pesanan dine in.');
}

// Kebalikan dari logika ongkir: di-null-kan kalau tidak relevan.
$mejaIdFinal = $hasDineIn ? (int) $data['meja_id'] : null;
```

Validasi lanjutan: meja harus ada, `gudang_id`-nya sama dengan gudang transaksi, `status_aktif = true`, dan belum punya sesi `terisi` — kecuali sesi milik order pending yang sedang diselesaikan.

### 5.5 Lifecycle sesi (di dalam transaksi yang sama)

| Alur | Aksi |
|---|---|
| `buatOrderPending()` | `INSERT sesi_meja` `status=terisi`, `mulai=now()`, `batas_menit=meja.durasi_menit`, `order_pending_id` |
| `simpanTransaksiPenjualan()` dengan `order_pending_id` | `UPDATE` → `status=selesai`, `selesai=now()`, isi `durasi_menit_terpakai` + `overstay`, isi `penjualan_id` |
| `simpanTransaksiPenjualan()` bayar langsung | buat sesi lalu langsung `selesai` dalam satu transaksi |
| `batalkanOrderPending()` | `UPDATE` → `status=batal`, `selesai=now()` |
| `lepasMeja($id)` | `UPDATE` sesi `terisi` → selesai/batal secara manual |

**Urutan lock** (mengikuti pola `order_pending`: lock dulu, baru baca):

1. `Meja::lockForUpdate()`
2. `SesiMeja::where('meja_id', …)->where('status', 'terisi')->lockForUpdate()`
3. Baru insert / update sesi

Partial unique index menjadi jaring pengaman terakhir kalau dua kasir lolos bersamaan.

### 5.6 Response yang diperbarui

- `daftarOrderPending()` — tambah `meja_nama`, `sisa_menit`, `overstay` untuk badge di panel Order
- `detailOrderPending()` — kembalikan `meja_id` agar keranjang bisa restore pilihan
- `formatRiwayatItem()` — `meja_nama` + `durasi_meja_menit` untuk riwayat & struk

## 6. Frontend

### 6.1 `resources/js/kasir/api.js`

`mockData.meja`, `getMeja(gudangId)`, `lepasMeja(sesiId)` — masing-masing dengan varian mock supaya mode mock tetap jalan.

### 6.2 `resources/js/kasir/kasir.js`

State baru: `meja`, `mejaId`, `mejaInfo`.

| Fungsi | Tugas |
|---|---|
| `bukaDashboardMeja()` / `tutupDashboardMeja()` | buka/tutup overlay, `muatUlangMeja()` |
| `renderDashboardMeja()` | grid kartu per warna status, badge `sisa 45m` / `OVER 12m`, tombol Lepas |
| `pilihMeja(id)` | set state, tutup overlay, `render()` |
| `keranjangPakaiMeja()` | apakah cart punya item `dine_in` |
| `muatUlangMeja()` | fetch + render |
| `lepasMeja(sesiId)` | konfirmasi → POST → toast → refresh |
| `formatDurasiMeja(menit)` | `45m` / `1j 12m` |

Titik integrasi: `buildPayload()` (+`meja_id`), `resetTransaksi()` (bersihkan), `prosesBayar()` (guard sebelum cek uang), `btnSimpanOrderPending()` (guard yang sama), `lanjutkanOrderPending()` (restore), `setJenisPesananGlobal()` (bersihkan saat bukan dine_in), `renderCart()` (blok ringkasan meja, paralel `aplikatorPicker`), `render()` (meja aktif).

Interval 15 detik saat overlay terbuka supaya countdown dan status meja dari kasir lain ikut bergerak.

### 6.3 `resources/views/kasir.blade.php`

- Overlay `#modal-dashboard-meja` sebelum `#modal-struk`
- Tombol **Meja** di sidebar + versi mobile, dengan badge jumlah meja terisi
- Struk (`tampilkanStruk()` di `kasir.js`): blok **Meja** + **Durasi** di header. Catatan: `struk-pembelian.blade.php` ada tapi tidak dipakai di mana pun — struk dirender penuh dari JS.

## 7. Admin Filament

`app/Filament/Resources/Mejas/` meniru struktur `Gudangs/`: `MejaResource.php`, `Schemas/MejaForm.php`, `Tables/MejasTable.php`, `Pages/{List,Create,Edit}Mejas.php`.

Ditambahkan juga kolom `meja` + `durasi_meja_menit` di `PenjualansTable.php` dan blok info di `filament/resources/penjualan/detail-modal.blade.php`.

## 8. Seeder

`database/seeders/MejaSeeder.php` — 12 meja per gudang dengan kode `Meja 01`–`Meja 12` (6 indoor 60m, 3 teras 45m, 2 VIP 90m, 1 VIP 120m). Didaftarkan di `MasterDataSeeder`.

## 9. Pengujian

### 9.1 `tests/Feature/MejaTest.php` (baru)

1. `GET /kasir/meja` → semua meja `tersedia`
2. `POST /kasir/simpan` dine_in tanpa `meja_id` → 422
3. take_away / delivery tanpa `meja_id` → berhasil (meja tidak relevan)
4. campur dine_in + take_away tanpa meja → 422
5. `meja_id` terkirim tanpa item dine_in → `penjualan.meja_id` null
6. order pending dengan meja → sesi `terisi`
7. meja terisi ditolak untuk order pending kedua → 422
8. `GET /kasir/meja` saat dipegang → `terisi` + `sisa_menit`
9. `batas_menit` terlampaui → `overstay = true`
10. bayar order pending → sesi `selesai`, `penjualan.meja_id` + `durasi_meja_menit` terisi, meja `tersedia`
11. batalkan order pending → sesi `batal`, meja `tersedia`
12. `meja_id` dari gudang lain → 422; `POST /kasir/meja/{sesi}/lepas` → meja bebas

### 9.2 Test lama yang perlu diperbarui

`meja_id` wajib untuk `dine_in`, dan `jenis_pesanan` default server adalah `dine_in`, jadi payload tanpa `jenis_pesanan` ikut terdampak — sekitar 45 call site di 8 file.

Strategi: `tests/Concerns/BuatMeja.php` dengan `buatMejaAktif()` dan `denganMeja()` untuk inject `meja_id`, lalu bungkus di builder payload tiap file (`OrderPendingTest::payloadOrder()`, dll). Test yang membuat `Penjualan::create()` langsung (semua `Rekapan*Test`, `LaporanPenjualanDetailTest`) tidak tersentuh karena `meja_id` nullable.

## 10. Catatan / Opsi Lanjutan (di luar cakupan sesi ini)

**Fase 2 — tahan meja setelah bayar.** Tambahkan `tahan_setelah_bayar` (bool) di `sesi_meja` + toggle di overlay. Kalau aktif, sesi tetap `terisi` setelah pembayaran dan hanya bisa dibebaskan lewat tombol **Lepas Meja**. Ini satu-satunya cara agar kasus "sudah bayar tapi masih lama duduk di meja" (motivasi awal fitur) ikut tertangani. Default **mati** supaya perilaku saat ini tetap "bebas setelah bayar".

**Opsi lain di luar cakupan:** dashboard monitor meja di Filament untuk superviseur, reorder meja (posisi grid), split bill per meja, dan integrasi `kapasitas` untuk peringatan exceed kapasitas.
