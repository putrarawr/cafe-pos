# Spesifikasi Desain: Filter Kategori Pembayaran pada Rekapan Kasir

## 1. Ringkasan Fitur
Menambahkan filter **Kategori Pembayaran** pada halaman Laporan **Rekapan Kasir** (`App\Filament\Pages\RekapanKasir`) untuk memungkinkan audit kasir dan proses *cash settlement* terfokus pada metode pembayaran tertentu (Tunai, QRIS, atau Bank/Transfer).

## 2. Cakupan dan Perilaku
Filter ini memengaruhi tiga area utama secara serentak:
1. **4 Kartu Ringkasan Atas (`overviewStats`)**:
   - Jika filter spesifik dipilih (contoh: `tunai`), kartu Tunai menampilkan total transaksi dan omset tunai aktual, kartu QRIS dan Transfer menjadi `0 TRANSAKSI` dan `Rp 0` dengan efek visual redup (*dimmed*), sedangkan kartu Total Keseluruhan mencerminkan total dari kategori aktif tersebut.
   - Jika `Semua Kategori` aktif, seluruh kartu menampilkan distribusi penuh sebagaimana mestinya.
2. **Tabel Rekapan Kasir (`table`)**:
   - Agregasi `subQuery` kasir menyaring transaksi penjualan sesuai kategori pembayaran terpilih.
   - Kolom tabel tetap mempertahankan 7 kolom standar (Nama Kasir, Total Transaksi, Total Qty Item, Total Tunai, Total QRIS, Total Bank / Transfer, Total Omset Kasir).
   - Kolom untuk kategori pembayaran yang tidak terpilih bernilai `Rp 0`.
3. **Modal Rincian Faktur (`modal-detail-rekapan-kasir`)**:
   - Menerima parameter kategori pembayaran terpilih.
   - Mengambil hanya faktur yang sesuai dengan kategori pembayaran tersebut.
   - Kartu mini pada header modal dan footer tabel faktur menyesuaikan total akumulasi faktur yang difilter.

## 3. Komponen Form Filter
- **Field Baru**: `Select::make('kategori_pembayaran')`
- **Label**: `Kategori Pembayaran`
- **Placeholder**: `Semua Kategori`
- **Opsi**:
  - `tunai` => Tunai
  - `qris` => QRIS
  - `transfer` => Bank / Transfer
- **Grid Layout**: Diperbarui menjadi `Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])` agar sejajar rapi dalam satu baris bersama `dari_tanggal`, `sampai_tanggal`, dan `kasir`.
- **Reaktivitas**: Dilengkapi `->live()` dan `->afterStateUpdated(fn () => $this->resetTable())`.
- **Inisialisasi Nilai Default (`mount`)**: `'kategori_pembayaran' => null`.

## 4. Spesifikasi Kueri Database (PostgreSQL Compliant)
Penyaringan `jenis_pembayaran` pada PostgreSQL dilakukan secara *case-insensitive* menggunakan fungsi `LOWER()`:
- `tunai`: `LOWER(penjualan.jenis_pembayaran) = 'tunai'`
- `qris`: `LOWER(penjualan.jenis_pembayaran) = 'qris'`
- `transfer`: `LOWER(penjualan.jenis_pembayaran) IN ('transfer', 'bank', 'debit')`

Kueri tetap mematuhi konvensi AGENTS.md:
- Memanggil `->reorder()` sebelum agregasi `selectRaw()`.
- Menggunakan `fromSub($subQuery, 'penjualan')` untuk agregasi tabel Filament.
- Eager loading relasi `karyawan`, `user`, dan `details.barang` untuk mencegah *N+1 query*.

## 5. UI/UX & Tampilan Visual (Clean Dark Monokrom)
- **Kartu Ringkasan (`rekapan-kasir.blade.php`)**:
  - Kartu non-aktif saat filter aktif diberi gaya inline:
    `opacity: 0.38; filter: grayscale(0.6); transition: opacity 0.2s ease-in-out;`
  - Kartu aktif dan kartu Total Keseluruhan mempertahankan warna kontras penuh (`#ffffff`, border `#3f3f46`, background `#18181b`).
- **Modal Pop-up Rincian (`modal-detail-rekapan-kasir.blade.php`)**:
  - Kartu mini pada header modal menerapkan perlakuan redup yang konsisten dengan halaman utama.
  - Jika tidak ada faktur untuk kategori yang dipilih, menampilkan pesan *empty state* yang jelas dan netral.

## 6. Rencana Pengujian & Validasi
1. Menjalankan pengujian otomatis `php artisan test` untuk memastikan tidak ada regresi pada modul laporan maupun transaksi.
2. Menjalankan `php artisan optimize:clear` untuk membersihkan cache tampilan dan konfigurasi.
3. Memvalidasi kueri agregasi berjalan lancar pada PostgreSQL tanpa error *grouping* (`SQLSTATE[42803]`).
