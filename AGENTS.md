
# AGENTS.md - Panduan Arsitektur, Standar Coding, dan Konvensi POS

Dokumen ini berisi kumpulan aturan kerja, konvensi teknis, standar arsitektur database, dan panduan UI/UX yang wajib dipatuhi oleh AI Agent dalam mengembangkan sistem Point of Sale (POS).

---

## 1. Aturan Kerja & Manajemen Proyek (Working Rules)

1. **Aturan Git Commit & Push:**
   - DILARANG melakukan `git add`, `git commit`, maupun `git push` secara otomatis kecuali ada perintah langsung dan eksplisit dari user (contoh: "commit", "push").
   - Format pesan commit: DILARANG menggunakan tanda titik dua (contoh dilarang: `feat: tambah menu`, `fix: perbaiki bug`). Tulis langsung kalimat aksi ringkas (contoh benar: `tambah halaman rekapan kasir dan modal rincian faktur`).
   - DILARANG menggunakan emoticon/emoji di pesan commit, kode, badge, maupun jawaban teks.
2. **Kualitas & Integritas Kode:**
   - DILARANG menuliskan kode potongan, placeholder, atau komentar menggantung seperti `// code goes here`, `// implement logic here`, atau `...`. Setiap perubahan file harus lengkap dan dapat langsung dieksekusi (*drop-in replacement*).
   - Setiap selesai mengimplementasikan fitur atau perbaikan logika, wajib menjalankan validasi pengujian otomatis `php artisan test` dan membersihkan cache aplikasi via `php artisan optimize:clear`.

---

## 2. Tech Stack & Standar Database

1. **Komponen Utama:**
   - Framework: Laravel 11/12
   - Admin & Panel System: Filament v3 (Livewire 3)
   - Database: PostgreSQL
   - Styling: Tailwind CSS & Alpine.js
2. **Konvensi PostgreSQL:**
   - Gunakan fungsi `ilike` untuk pencarian teks agar bersifat *case-insensitive*.
   - Pada kueri agregasi tabel Filament (`SUM`, `COUNT`, `AVG`), pastikan memanggil `->reorder()` terlebih dahulu sebelum `selectRaw()` agregasi untuk menghapus klausa `ORDER BY` bawaan dan mencegah error PostgreSQL `SQLSTATE[42803]: Grouping error`.
   - Untuk tabel agregasi kompleks (seperti pengelompokan kasir atau produk), bungkus kueri menggunakan pola `fromSub($subQuery, 'alias')` agar paginasi dan pengurutan bawaan Filament berjalan aman tanpa konflik `GROUP BY`.
3. **Pencegahan Masalah N+1:**
   - Seluruh relasi data yang ditampilkan di tabel, kartu metrik, maupun modal pop-up wajib di-*eager load* menggunakan `with(['relasi1', 'relasi2.subrelasi'])`.
   - Hindari pemanggilan relasi berulang di dalam accessor loop.

---

## 3. Standar UI/UX & Desain Interface

1. **Skema Warna Netral & Monokrom (Clean Dark Aesthetic):**
   - Utamakan penggunaan warna monokrom/netral (palet Zinc/Gray: `#18181b`, `#202024`, `#27272a`, `#3f3f46`).
   - Hindari penggunaan warna-warni kontras/neon yang ramai pada kartu dashboard. Gunakan teks putih tegas (`#ffffff`) dan abu-abu bersih (`#a1a1aa` / `#d4d4d8`).
2. **Tata Letak & Spacing Lega:**
   - Gunakan jarak pemisah yang cukup antar blok komponen (`gap: 24px` hingga `gap: 28px`).
   - Berikan ruang padding yang lega pada sel tabel (`padding: 14px 16px` pada `th` dan `16px 16px` pada `td`).
   - Terapkan `border-radius` yang proporsional dan tidak berlebihan:
     - Tombol dan field input: `6px`
     - Badge status: `4px`
     - Kartu kontainer dan tabel: `8px`
3. **Pola Interaksi Tabel:**
   - Utamakan interaksi klik baris langsung (`$table->recordAction('detail')`) untuk melihat rincian dibanding menambahkan kolom aksi (*action column*) yang memakan lebar tabel.
   - Sembunyikan tombol aksi bawaan yang tidak perlu dengan atribut `hidden` / `display:none`.
4. **Desain Pop-up Modal Rincian:**
   - Atur lebar modal ke ukuran proporsional (`modalWidth('5xl')` atau `modalWidth('7xl')`).
   - Hilangkan tombol submit jika modal hanya bersifat *viewing* (`->modalSubmitAction(false)` dan `->modalCancelActionLabel('Tutup')`).
   - Sediakan kontainer scroll vertikal mandiri (`max-height: 420px; overflow-y: auto;`) lengkap dengan *sticky header* (`thead th`) dan *sticky footer* (`tfoot td`) agar konteks kolom dan total akumulasi tidak hilang saat data di-scroll.
5. **Modul Laporan & Rekapan:**
   - Seluruh halaman laporan dan rekapan bersifat murni **Read-Only** (hanya menampilkan ringkasan data, filter periode, dan audit riwayat tanpa form manipulasi data transaksi).
   - Form filter di bagian atas dibuat ringkas menggunakan `Section::make()` tanpa header panjang, serta bersifat reaktif instan (`live()` dipadukan dengan `afterStateUpdated(fn () => $this->resetTable())`).

---

## 4. Logika Bisnis & Transaksi POS

1. **Struktur Item Penjualan & Promo Bonus (1 Baris Transaksi):**
   - Transaksi item utama dan item bonus promo dicatat dalam baris `detail_jual` yang sama untuk integritas data audit:
     - `barang_id`: ID produk utama yang dibeli pelanggan.
     - `jumlah`, `harga`, `satuan`, `hpp`, `subtotal`: Data pembelian reguler.
     - `bonus_barang_id`, `bonus_qty`, `bonus_satuan`, `bonus_hpp`: Data item bonus yang keluar (jika memenuhi promo Buy X Get Y).
     - `is_bonus`: Bernilai `false` untuk transaksi belanja normal dan `true` jika baris tersebut merupakan item bonus murni.
2. **Perhitungan Laba Kotor / Margin Bersih Transaksi:**
   - Beban HPP dari barang bonus harus diperhitungkan sebagai pengurang laba kotor:
     $$
     \text{Laba Bersih} = \text{Subtotal Omset} - (\text{Jumlah Reguler} \times \text{HPP Reguler}) - (\text{Qty Bonus} \times \text{HPP Bonus})
     $$
3. **Audit Kasir & Rekapan Metode Pembayaran:**
   - Klasifikasikan metode pembayaran ke dalam 3 kategori standar:
     - **Tunai**: Uang kas fisik di laci kasir (mencatat nominal `bayar` dan `kembalian`).
     - **QRIS**: Pembayaran non-tunai instan.
     - **Bank / Transfer**: Transaksi via EDC, kartu debit, atau transfer bank.
   - Rekapan kasir wajib memecah total omset per metode pembayaran untuk mempermudah proses *cash settlement* saat pergantian shift atau penutupan toko (*closing*).
4. **Sistem Satuan Bertingkat (Multi-Level Unit):**
   - Setiap produk memiliki Satuan Dasar (Level 1).
   - Seluruh mutasi stok pada gudang (`barang_gudang`) dan kartu stok (`kartu_stok`) selalu dikonversi dan disimpan dalam Satuan Dasar (Level 1) untuk menjaga konsistensi kuantitas fisik.

---

## 5. Standar Filament v3 Page & Component Structure

Contoh struktur standar Custom Page Filament v3:

```php
<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class RekapanContoh extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | array $routePath = 'rekapan-contoh';

    protected static ?string $title = 'Rekapan Contoh';

    protected static ?string $navigationLabel = 'Rekapan Contoh';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'filament.pages.rekapan-contoh';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_tanggal' => now()->format('Y-m-d'),
            'sampai_tanggal' => now()->format('Y-m-d'),
        ]);
    }

    public function updatedData(): void
    {
        $this->resetTable();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('dari_tanggal')
                                ->label('Dari Tanggal')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            DatePicker::make('sampai_tanggal')
                                ->label('Sampai Tanggal')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(...)
            ->columns([...]);
    }
}
```
