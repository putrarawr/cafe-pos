<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestStokMenipisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gudangs = Gudang::all();
        if ($gudangs->isEmpty()) {
            return;
        }

        $gudangUtama = $gudangs->firstWhere('nama_gudang', 'Gudang Utama (Pusat)') ?? $gudangs->first();
        $jenisMinuman = JenisBarang::firstOrCreate(['nama_jenis' => 'Minuman Kemasan'], ['kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);
        $jenisRokok = JenisBarang::firstOrCreate(['nama_jenis' => 'Rokok'], ['kode_jenis' => 'ROK', 'deskripsi' => 'Rokok']);
        $jenisKemasan = JenisBarang::firstOrCreate(['nama_jenis' => 'Kemasan & Perlengkapan'], ['kode_jenis' => 'KMS', 'deskripsi' => 'Kemasan']);

        // ----------------------------------------------------------------------------------------------------
        // Skenario 1: Global Menipis (Total Toko <= Batas Global, Stok Tiap Gudang Aman)
        // ----------------------------------------------------------------------------------------------------
        $b1 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Kopi Bubuk Robusta 250g'],
            [
                'jenis_barang_id' => $jenisMinuman->id,
                'tipe_barang' => 'barang_dagang',
                'satuan' => 'Bungkus',
                'harga_beli' => 15000,
                'hpp' => 15000,
                'harga_jual' => 20000,
                'stok_minimum' => 100, // Batas Global tinggi: 100 Bungkus
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        // Tiap gudang diset stok 15 (di atas batas gudang 10), tapi total 5 gudang = 75 <= 100 (Global Menipis)
        foreach ($gudangs as $g) {
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b1->id, 'gudang_id' => $g->id],
                ['stok' => 15, 'stok_minimum' => 10, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 2: Satu Gudang Menipis (Gudang Utama Menipis, Gudang Lain & Global Aman)
        // ----------------------------------------------------------------------------------------------------
        $b2 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Susu Kental Manis Kaleng'],
            [
                'jenis_barang_id' => $jenisMinuman->id,
                'tipe_barang' => 'barang_dagang',
                'satuan' => 'Kaleng',
                'harga_beli' => 12000,
                'hpp' => 12000,
                'harga_jual' => 15000,
                'stok_minimum' => 30, // Batas Global: 30 Kaleng
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        foreach ($gudangs as $g) {
            $isUtama = ($g->id === $gudangUtama->id);
            // Gudang Utama hanya 5 Kaleng (<= min 20), gudang lain 40 Kaleng (total 165 > 30, global aman)
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b2->id, 'gudang_id' => $g->id],
                ['stok' => $isUtama ? 5 : 40, 'stok_minimum' => 20, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 3: Semua Gudang Menipis Tapi Global Aman (Batas Gudang 15, Stok 10, Batas Global Rendah 20)
        // ----------------------------------------------------------------------------------------------------
        $b3 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Sirup Vanila 750ml'],
            [
                'jenis_barang_id' => $jenisMinuman->id,
                'tipe_barang' => 'barang_dagang',
                'satuan' => 'Botol',
                'harga_beli' => 25000,
                'hpp' => 25000,
                'harga_jual' => 32000,
                'stok_minimum' => 20, // Batas Global rendah: 20 Botol
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        // Tiap gudang 10 Botol (<= min gudang 15). Total 5 gudang = 50 Botol (> min global 20)
        foreach ($gudangs as $g) {
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b3->id, 'gudang_id' => $g->id],
                ['stok' => 10, 'stok_minimum' => 15, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 4: Satuan Bertingkat (Konversi Pres/Slof & Bungkus di Badge Alert)
        // ----------------------------------------------------------------------------------------------------
        $b4 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Rokok Filter Herbal'],
            [
                'jenis_barang_id' => $jenisRokok->id,
                'tipe_barang' => 'barang_dagang',
                'satuan' => 'Bungkus',
                'satuan_2' => 'Pres/Slof',
                'isi_satuan_2' => 10,
                'harga_beli' => 20000,
                'hpp' => 20000,
                'harga_jual' => 24000,
                'harga_beli_2' => 195000,
                'harga_jual_2' => 235000,
                'stok_minimum' => 50, // 5 Pres/Slof
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        foreach ($gudangs as $g) {
            $isUtama = ($g->id === $gudangUtama->id);
            // Gudang Utama 14 Bungkus (1 Slof, 4 Bungkus <= min 30 / 3 Slof). Gudang lain 2 Bungkus. Total 22 <= 50.
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b4->id, 'gudang_id' => $g->id],
                ['stok' => $isUtama ? 14 : 2, 'stok_minimum' => $isUtama ? 30 : 10, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 5: Stok Habis Total (Stok 0 di Seluruh Toko & Gudang)
        // ----------------------------------------------------------------------------------------------------
        $b5 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Green Tea Matcha Powder'],
            [
                'jenis_barang_id' => $jenisMinuman->id,
                'tipe_barang' => 'barang_dagang',
                'satuan' => 'Pack',
                'harga_beli' => 30000,
                'hpp' => 30000,
                'harga_jual' => 40000,
                'stok_minimum' => 20,
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        foreach ($gudangs as $g) {
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b5->id, 'gudang_id' => $g->id],
                ['stok' => 0, 'stok_minimum' => 10, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 6: Pemantauan Dinonaktifkan (Stok 0, tapi Toggle OFF / Min 0 -> TIDAK MUNCUL DI ALERT)
        // ----------------------------------------------------------------------------------------------------
        $b6 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Sendok Kayu Disposable'],
            [
                'jenis_barang_id' => $jenisKemasan->id,
                'tipe_barang' => 'kemasan',
                'satuan' => 'Pcs',
                'harga_beli' => 500,
                'hpp' => 500,
                'harga_jual' => 800,
                'stok_minimum' => 0, // Toggle Global OFF
                'bisa_dijual' => false,
                'status' => 'tersedia',
            ]
        );
        foreach ($gudangs as $g) {
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b6->id, 'gudang_id' => $g->id],
                ['stok' => 0, 'stok_minimum' => 0, 'updated_at' => now(), 'created_at' => now()] // Toggle Gudang OFF
            );
        }

        // ----------------------------------------------------------------------------------------------------
        // Skenario 7: Menu Olahan / Barang Jadi (Stok 0, tipe barang_jadi -> TIDAK MUNCUL DI ALERT)
        // ----------------------------------------------------------------------------------------------------
        $b7 = Barang::updateOrCreate(
            ['nama_barang' => '[TEST] Espresso Single Shot'],
            [
                'jenis_barang_id' => $jenisMinuman->id,
                'tipe_barang' => 'barang_jadi', // Menu racikan cafe
                'satuan' => 'Cup',
                'harga_beli' => 5000,
                'hpp' => 5000,
                'harga_jual' => 15000,
                'stok_minimum' => 0,
                'bisa_dijual' => true,
                'status' => 'tersedia',
            ]
        );
        foreach ($gudangs as $g) {
            DB::table('barang_gudang')->updateOrInsert(
                ['barang_id' => $b7->id, 'gudang_id' => $g->id],
                ['stok' => 0, 'stok_minimum' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
