# Spesifikasi Desain: Penambahan Field Cafe pada Model Barang

## 1. Ringkasan Fitur
Menambahkan field pendukung operasional Cafe dan F&B pada entitas `Barang`:
1. `gambar` (string, nullable)
2. `tipe_barang` (string/enum: `bahan_baku`, `setengah_jadi`, `barang_jadi`, `barang_dagang`, `barang_pembantu`)
3. `status` (string/enum: `tersedia`, `habis`, default: `tersedia`)
4. `butuh_proses` (boolean, default: `false`)
5. `bisa_dijual` (boolean, default: `true`)

Fitur ini mengintegrasikan administrasi barang di Filament v3 dengan visual katalog dan status ketersediaan pada antarmuka kasir POS.

## 2. Skema Database & Migrasi
- **Nama Migrasi**: `xxxx_xx_xx_xxxxxx_add_cafe_fields_to_barang_table.php`
- **Kolom Baru pada Tabel `barang`**:
  - `gambar`: `$table->string('gambar')->nullable()->after('nama_barang');`
  - `tipe_barang`: `$table->string('tipe_barang')->default('barang_dagang')->after('jenis_barang_id');`
  - `status`: `$table->string('status')->default('tersedia')->after('satuan');`
  - `butuh_proses`: `$table->boolean('butuh_proses')->default(false)->after('status');`
  - `bisa_dijual`: `$table->boolean('bisa_dijual')->default(true)->after('butuh_proses');`
- **Penanganan Data Lama**:
  - Seluruh data barang yang sudah ada otomatis diberi nilai `tipe_barang = 'barang_dagang'`, `status = 'tersedia'`, `bisa_dijual = true`, dan `butuh_proses = false`.
  - Pada level form Filament, kolom `tipe_barang` tidak memiliki default otomatis sehingga wajib dipilih secara eksplisit oleh admin saat menambah barang baru.

## 3. Penyesuaian Model `Barang.php`
- Menambahkan kolom-kolom baru ke dalam `$fillable` / `$guarded` dan log aktivitas (`getActivitylogOptions`):
  - `gambar`, `tipe_barang`, `status`, `butuh_proses`, `bisa_dijual`.
- Menambahkan helper accessor / method:
  - `getGambarUrlAttribute()`: Mengembalikan URL publik dari file foto (`asset('storage/' . $this->gambar)`) atau null jika kosong.
  - Scope query: `scopeBisaDijual($query)` untuk menyaring hanya barang yang dapat dijual di kasir.

## 4. Administrasi Filament Panel
- **Form Barang (`BarangForm.php`)**:
  - Menambahkan Section baru: `'Pengaturan Cafe & POS'` di bawah Informasi Dasar.
  - Komponen dalam section:
    - `FileUpload::make('gambar')`: Direktori `barang`, disk `public`, validasi image (jpg, jpeg, png, webp, max 2MB).
    - `Select::make('tipe_barang')`: Wajib diisi, opsi:
      - `bahan_baku` => 'Bahan Baku'
      - `setengah_jadi` => 'Setengah Jadi'
      - `barang_jadi` => 'Barang Jadi'
      - `barang_dagang` => 'Barang Dagang'
      - `barang_pembantu` => 'Barang Pembantu'
      - Reaktivitas:
        - Jika dipilih `bahan_baku`, `setengah_jadi`, `barang_pembantu`: `bisa_dijual` diatur ke `false`.
        - Jika dipilih `barang_jadi`, `barang_dagang`: `bisa_dijual` diatur ke `true`.
        - Jika dipilih `barang_jadi`: `butuh_proses` diatur ke `true`. Jika tipe lain: `butuh_proses` diatur ke `false`.
    - `Toggle::make('bisa_dijual')`: Label 'Dapat Dijual di Kasir', helper text 'Menentukan apakah produk muncul pada katalog layar kasir'.
    - `Toggle::make('butuh_proses')`: Label 'Butuh Proses (Dapur/Bar)', helper text 'Menandai pesanan harus diproses koki/barista'.
    - `Toggle::make('status')`: Label 'Status Ketersediaan', default true (On = Tersedia, Off = Habis).
- **Tabel Barang (`BarangsTable.php`)**:
  - Kolom foto `ImageColumn::make('gambar')` melingkar dengan disk publik.
  - Kolom `TextColumn::make('tipe_barang')` dengan format label bersih dan badge netral.
  - Kolom `ToggleColumn::make('status')`: Mengubah status ketersediaan menu secara langsung dari tabel.
  - Filter `SelectFilter::make('tipe_barang')` dan `SelectFilter::make('status')`.

## 5. Integrasi Layar Kasir / POS
- **Backend Controller (`KasirController.php`)**:
  - Kueri katalog kasir: `Barang::where('bisa_dijual', true)->with('gudangs')->get()`
  - Payload JSON `kasirData.barang` memuat: `gambar` (URL), `tipe_barang`, `status`, `butuh_proses`.
- **Frontend Kasir (`kasir.js`)**:
  - Menampilkan thumbnail gambar jika ada pada kartu produk di grid.
  - Memeriksa kondisi ketersediaan: `const habis = b.status === 'habis' || stok <= 0;`.
  - Jika `habis`, tombol produk di-disable dengan tampilan buram (*opacity: 0.4*) serta badge 'Habis'.

## 6. Rencana Pengujian Otomatis
- Feature Test `tests/Feature/BarangCafeFieldsTest.php`:
  1. Memvalidasi migrasi dan struktur kolom baru pada tabel `barang`.
  2. Memvalidasi data lama otomatis menjadi `barang_dagang`.
  3. Memvalidasi endpoint kasir hanya mengembalikan barang dengan `bisa_dijual = true`.
  4. Memvalidasi perubahan status ketersediaan 'tersedia' / 'habis' berfungsi secara tepat.
