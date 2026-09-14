# Spesifikasi Desain: Master Data Aplikator & Harga Delivery per Aplikator pada Barang

## 1. Ringkasan Fitur
Memisahkan penetapan harga dasar antara pesanan reguler (Dine In & Take Away) dengan pesanan Delivery berbasis platform mitra / aplicator (GoFood, GrabFood, ShopeeFood, Maxim, Kurir Toko, dll):
- Menyediakan Master Data **Aplikator** untuk mendata channel/platform penjualan online.
- Pada formulir pengelolaan barang di admin (`/admin/barangs/{id}/edit`), admin dapat menambahkan daftar harga jual khusus delivery per aplikator menggunakan tabel repeater (serupa dengan penambahan item pada transaksi pembelian barang).
- Tiap item barang dapat memiliki harga delivery yang berbeda-beda untuk tiap aplikator, atau tidak diset jika belum dijual di platform terkait.

---

## 2. Skema Database & Migrasi

### Tabel `aplikator`
- File Migrasi: `2026_09_14_111000_create_aplikators_table.php`
- Kolom:
  - `id`: bigIncrements
  - `nama_aplikator`: string (misal: "GoFood", "GrabFood", "ShopeeFood", "Maxim Food", "Kurir Toko")
  - `kode_aplikator`: string nullable unique (misal: "GOFOOD", "GRABFOOD", "SHOPEE")
  - `persentase_komisi`: decimal(5, 2) default 0 (untuk referensi potongan platform)
  - `keterangan`: text nullable
  - `status_aktif`: boolean default true
  - timestamps

### Tabel `barang_harga_aplikator`
- File Migrasi: `2026_09_14_111500_create_barang_harga_aplikator_table.php`
- Kolom:
  - `id`: bigIncrements
  - `barang_id`: foreignId constrained to `barang` cascadeOnDelete
  - `aplikator_id`: foreignId constrained to `aplikator` cascadeOnDelete
  - `harga_jual`: unsignedBigInteger / numeric (harga jual khusus di aplikator tersebut)
  - `unique(['barang_id', 'aplikator_id'])`: mencegah duplikasi aplikator pada barang yang sama
  - timestamps

---

## 3. Eloquent Model

### Model `Aplikator` (`app/Models/Aplikator.php`)
- Relasi:
  - `barangHargaAplikators()`: `hasMany(BarangHargaAplikator::class, 'aplikator_id')`
  - `barangs()`: `belongsToMany(Barang::class, 'barang_harga_aplikator')->withPivot('harga_jual')->withTimestamps()`
- Scope:
  - `scopeAktif($query)`: menyaring aplikator yang berstatus aktif.

### Model `BarangHargaAplikator` (`app/Models/BarangHargaAplikator.php`)
- Relasi:
  - `barang()`: `belongsTo(Barang::class, 'barang_id')`
  - `aplikator()`: `belongsTo(Aplikator::class, 'aplikator_id')`

### Model `Barang` (`app/Models/Barang.php`)
- Menambahkan relasi:
  - `hargaAplikators()`: `hasMany(BarangHargaAplikator::class, 'barang_id')`
  - `aplikators()`: `belongsToMany(Aplikator::class, 'barang_harga_aplikator')->withPivot('harga_jual')->withTimestamps()`

---

## 4. Filament Panel Admin

### Resource Baru: `AplikatorResource` (`/admin/aplikators`)
- Menu Admin: `Aplikator / Channel Delivery`
- Form:
  - `TextInput::make('nama_aplikator')->required()->maxLength(255)`
  - `TextInput::make('kode_aplikator')->placeholder('Contoh: GOFOOD')->maxLength(50)`
  - `TextInput::make('persentase_komisi')->label('Estimasi Komisi / Potongan Platform (%)')->numeric()->suffix('%')->default(0)`
  - `Toggle::make('status_aktif')->label('Status Aktif')->default(true)`
  - `Textarea::make('keterangan')->rows(2)`
- Tabel:
  - Kolom nama aplikator, kode, persentase komisi, toggle status aktif.

### Pembaruan `BarangForm.php`
- Menambahkan Section baru: **"Harga Delivery / Aplikator Online"**:
  - Menggunakan `Repeater::make('hargaAplikators')`:
    - Relationship: `hargaAplikators`
    - Skema:
      - `Select::make('aplikator_id')`: Pilih Aplikator, distinct & `disableOptionsWhenSelectedInSiblingRepeaterItems()`
      - `TextInput::make('harga_jual')`: `label('Harga Jual Delivery')`, numeric, prefix `Rp`, required.
    - Tombol tambah: `+ Tambah Harga Aplikator`

---

## 5. Seeder
- `AplikatorSeeder.php` mendaftarkan data awal:
  - GoFood (20%)
  - GrabFood (20%)
  - ShopeeFood (20%)
  - Maxim Food (15%)
  - Kurir Toko / Delivery Internal (0%)
