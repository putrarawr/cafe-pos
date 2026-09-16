# Spesifikasi Desain: Pilihan Wajib Aplikator pada Transaksi Delivery (Kasir)

## 1. Ringkasan Fitur
Menambahkan pilihan aplikator (GoFood, GrabFood, ShopeeFood, Maxim Food, Kurir Toko) yang **wajib dipilih** saat transaksi kasir mengandung item dengan tipe pesanan `delivery`:
- Pilihan aplikator muncul otomatis di panel kasir hanya ketika ada item delivery di keranjang.
- Transaksi diblokir (tidak bisa bayar) bila ada item delivery tapi aplikator belum dipilih.
- Aplikator dipilih **per transaksi** (disimpan di kolom `penjualan.aplikator_id`), bukan per item.
- Aplikator yang tampil hanya yang `status_aktif = true`.
- Tipe `take_away` / `dine_in` TIDAK membutuhkan aplikator; `aplikator_id` diabaikan oleh backend bila tidak ada item delivery.
- Ruang lingkup sesi ini: **pilihan wajib saja**. Harga tetap normal (`harga_jual`); belum menerapkan tarif khusus aplikator (`barang_harga_aplikator`).

---

## 2. Skema Database & Migrasi

### Tabel `penjualan` — tambah kolom `aplikator_id`
- File Migrasi: `2026_09_15_100000_add_aplikator_id_to_penjualan_table.php`
- Kolom:
  - `aplikator_id`: `foreignId()->nullable()->constrained('aplikator')->nullOnDelete()`
- Backward compatible: nilai `null` mengizinkan transaksi lama/non-delivery tetap valid.

---

## 3. Eloquent Model

### Model `Penjualan` (`app/Models/Penjualan.php`)
- `aplikator_id` ditambahkan ke `$fillable`.
- Relasi baru:
  - `aplikator()`: `belongsTo(Aplikator::class, 'aplikator_id')`

---

## 4. Backend Kasir (`app/Http/Controllers/KasirController.php`)

### Endpoint `index()` & `data()`
- Menambah payload `aplikator`: daftar aplikator `aktif` (urutan nama) untuk dirender sebagai pilihan di kasir.

### Endpoint `simpan()`
- Validasi:
  - `aplikator_id` => `nullable`, `exists:aplikator,id`
  - Rule aplikatif: bila transaksi memiliki detail `jenis_pesanan = delivery` tetapi `aplikator_id` kosong → **422** dengan pesan `"Aplikator wajib dipilih untuk pesanan delivery."`
- Determinasi `$hasDelivery`: `collect($data['details'])->contains('jenis_pesanan' => 'delivery')`.
- Simpan: `aplikator_id` hanya di-set (disimpan) bila `$hasDelivery` bernilai `true`; bila tidak ada delivery, nilai selalu `null` (input diabaikan).
- `$hasDelivery` di-capture ke closure transaksi (variabel `use (...)`) agar tersedia di dalam `DB::transaction`.

### Endpoint `riwayat()`, `verifikasiCetak()`, & helper `formatRiwayatItem()`
- `eagerLoad` menambah `aplikator` untuk menampilkan nama aplikator di riwayat.
- `formatRiwayatItem()` mengembalikan `aplikator_id` dan `aplikator` (object/nama) untuk konsumsi frontend (struk & cetak ulang).

---

## 5. Frontend Kasir (Vanilla JS)

### `resources/js/kasir/api.js`
- Menambah data `aplikator` (pada sesi ini berupa mock, meniru respon backend) + fungsi `getAplikator()`.

### `resources/js/kasir/kasir.js`
- **State**: `aplikator` (daftar dari API) dan `aplikatorId` (pilihan aktif).
- **Render (`renderCart`)**: mengambil `getAplikator()`. Bila ada item delivery:
  - Tampilkan blok `[data-aplikator-picker]` (judul "Aplikator Delivery" wajib).
  - Grid tombol pilihan `[data-aplikator-option]` dengan gaya yang konsisten dengan picker lain (warna berubah saat aktif).
- **Delegasi klik** `[data-aplikator-option]`: set `aplikatorId`, re-render ringkasan.
- **Sinkronisasi**: bila item delivery terakhir dihapus → `aplikatorId` direset dan picker disembunyikan.
- **Validasi proses bayar (`prosesBayar`)**: bila ada item delivery dan `aplikatorId` kosong → toast peringatan + `scrollIntoView` ke picker, proses dihentikan.
- **Payload** (`buildPayload`) & **preview struk**: menyertakan `aplikator_id`; baris struk `Via: {nama aplikator}` untuk delivery.
- **Riwayat / cetak ulang (`cetakUlangRiwayat`)**: meneruskan `aplikator` ke `tampilkanStruk()`.
- **Reset transaksi** & **ubah jenis pesanan global**: `aplikatorId` direset.
- **Perbaikan bug terkait yang ditemukan**: handler `input` untuk `input-alamat-pengiriman` / `input-biaya-kirim` mengarah ke elemen lama yang sudah dihapus (tidak aktif lagi). Kini ditangani via delegasi `input` di `#cart-items` + helper `updateRingkasanNeto()` sehingga alamat & ongkir tersimpan ke state tanpa kehilangan fokus saat mengetik ongkir.

---

## 6. Admin Filament

### Tabel `PenjualansTable.php`
- Kolom baru: `aplikator.nama_aplikator` — badge `info`/kotak kecil; fallback placeholder `'—'` bila null.
- Filter baru: `SelectFilter::make('aplikator_id')` — Pilih Aplikator.
- Detail modal (`load`): menambah `'aplikator'` ke eager-load.

### `detail-modal.blade.php`
- Bila `penjualan.aplikator` ada: menampilkan baris **Aplikator Delivery**, **Biaya Kirim**, dan **Alamat Pengiriman**.

---

## 7. Pengujian

### File baru: `tests/Feature/PilihanAplikatorDeliveryTest.php`
1. `kasir.data` hanya mengembalikan aplikator aktif (aplikator nonaktif tidak muncul).
2. Delivery tanpa `aplikator_id` → **422**, transaksi tidak tersimpan.
3. Delivery dengan `aplikator_id` → **200**, tersimpan, dan riwayat memuat nama aplikator.
4. `dine_in` tanpa `aplikator_id` → **200**, `aplikator_id` tersimpan `null`.
5. `dine_in` dengan `aplikator_id` dikirim → diabaikan (tersimpan `null`).

### Pembaruan tes lama (sesuai aturan baru)
- `BarangCafeFieldsTest::test_kasir_store_allows_delivery_without_address`: payload delivery kini menyertakan `aplikator_id` (membuat aplikator GoFood).
- `KemasanDanTipePesananTest::test_kasir_simpan_with_delivery_and_kemasan`: payload delivery kini menyertakan `aplikator_id`.

### Hasil
- Seluruh suite `php artisan test`: **68 tests passed** (collation PostgreSQL warning bersifat non-fatal).

---

## 8. Catatan / Opsi Lanjutan (di luar cakupan sesi ini)
- Menerapkan harga delivery khusus aplikator (`barang_harga_aplikator`) ke perhitungan total saat sesi berikutnya.
- Menghitung komisi/potongan aplikator & pelaporan per aplikator di rekapan kasir.
- Sinkronisasi data aplikator dari API aktual menggantikan mock di `api.js` bila backend sudah menyediakannya.