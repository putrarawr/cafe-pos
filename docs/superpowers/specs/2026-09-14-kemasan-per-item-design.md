# Spesifikasi Desain: Pengaturan Kemasan Default Per-Item Menu & Otomatisasi Kasir

## 1. Ringkasan Fitur
Mengubah mekanisme kemasan dari yang sebelumnya bersifat global (1 kemasan untuk seluruh toko) menjadi **berbasis per-item menu**:
- Setiap produk/menu (misal *Iced Latte*, *Hot Americano*, *Nasi Goreng*) dapat memiliki referensi kemasan default masing-masing (misal *Cup Dingin 16oz*, *Hot Paper Cup 8oz*, *Lunch Box Kraft*).
- Produk yang tidak memerlukan kemasan (misal air mineral kemasan botol pabrik atau rokok) dapat mengosongkan pilihan kemasan default.
- Pada saat kasir memilih pesanan **Take Away** atau **Delivery**, sistem POS secara cerdas menghitung dan memasukkan item kemasan yang sesuai secara proporsional dengan jumlah menu yang dipesan.
- Kasir tetap memiliki fleksibilitas dengan toggle **"Bawa Wadah Sendiri"** untuk menghapus seluruh kemasan jika pelanggan membawa tumbler/wadah sendiri.

---

## 2. Skema Database & Migrasi
- **File Migrasi**: `2026_09_14_103000_add_kemasan_id_to_barang_table.php`
- **Kolom Baru pada Tabel `barang`**:
  ```php
  $table->foreignId('kemasan_id')
      ->nullable()
      ->after('tipe_barang')
      ->constrained('barang')
      ->nullOnDelete();
  ```
- **Integritas Relasi**: Menggunakan `constrained('barang')->nullOnDelete()` sehingga jika suatu barang kemasan dihapus dari sistem, referensi pada menu utama akan otomatis di-null-kan tanpa merusak data menu.

---

## 3. Penyesuaian Model Eloquent (Barang.php)
- **Relasi**:
  ```php
  public function kemasan(): BelongsTo
  {
      return $this->belongsTo(Barang::class, 'kemasan_id');
  }

  public function barangPenggunaKemasan(): HasMany
  {
      return $this->hasMany(Barang::class, 'kemasan_id');
  }
  ```
- **Activity Log**: Mendaftarkan `kemasan_id` ke dalam `logOnly` di `getActivitylogOptions()`.

---

## 4. Administrasi Filament Panel (/admin/barangs)

### Form Barang (BarangForm.php)
- Pada seksi **Pengaturan Cafe & POS**:
  - Menambahkan field:
    ```php
    Select::make('kemasan_id')
        ->label('Kemasan Default (Take Away / Delivery)')
        ->placeholder('Pilih kemasan default... (Kosongkan jika tanpa kemasan)')
        ->relationship(
            'kemasan',
            'nama_barang',
            modifyQueryUsing: fn ($query) => $query->whereIn('tipe_barang', ['kemasan', 'barang_pembantu'])->where('status', 'tersedia')
        )
        ->searchable()
        ->preload()
        ->helperText('Kemasan yang otomatis disertakan saat menu ini dipesan untuk Take Away atau Delivery.')
        ->hidden(fn ($get) => in_array($get('tipe_barang'), ['kemasan', 'barang_pembantu'])),
    ```

### Tabel Barang (BarangsTable.php)
- Menambahkan kolom `TextColumn::make('kemasan.nama_barang')` dengan badge netral untuk melihat asosiasi kemasan pada tiap menu secara transparan.

---

## 5. Integrasi Backend Kasir (KasirController.php)
- Menambahkan eager load `with(['gudangs', 'kemasan'])` pada katalog `barang`.
- Memetakan `kemasan_id` dan objek ringkas kemasan default pada payload JSON kasir:
  ```php
  'kemasan_id' => $b->kemasan_id,
  'kemasan' => $b->kemasan ? [
      'id' => $b->kemasan->id,
      'nama_barang' => $b->kemasan->nama_barang,
      'harga_jual' => (int) $b->kemasan->harga_jual,
      'satuan' => $b->kemasan->satuan,
  ] : null,
  ```

---

## 6. Logika Antarmuka Kasir (kasir.js)
1. **Otomatisasi Sinkronisasi Kemasan (`sinkronkanKemasanOtomatis()`)**:
   - Jika tipe pesanan adalah `take_away` atau `delivery`, dan toggle `pakaiKemasan === true`:
     - Menghitung agregasi kebutuhan kemasan dari semua item menu reguler di keranjang:
       Kebutuhan Kemasan X = SUM(Qty Menu i yang memiliki kemasan_id = X)
     - Menyesuaikan baris item kemasan di keranjang belanja agar kuantitasnya sama persis dengan total kebutuhan.
     - Jika kuantitas menu bertambah atau berkurang, kuantitas kemasan terkait langsung sinkron.
     - Jika menu dihapus dari keranjang, kemasan yang sudah tidak dibutuhkan otomatis terhapus.
2. **Fleksibilitas Pelanggan**:
   - Tombol toggle **"Bawa Wadah Sendiri"**: Ketika diklik oleh kasir, `pakaiKemasan = false`, dan seluruh item kemasan langsung dikeluarkan dari keranjang belanja.
   - Kasir juga tetap dapat mengubah kuantitas atau menghapus baris kemasan tertentu di tabel keranjang jika ada penyesuaian khusus.
