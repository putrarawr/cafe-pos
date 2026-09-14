<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use Illuminate\Database\Seeder;

class KemasanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allGudangs = Gudang::all();

        // 1. Kategori / Jenis Barang Kemasan
        $jenis = JenisBarang::firstOrCreate(
            ['nama_jenis' => 'Kemasan & Perlengkapan'],
            [
                'kode_jenis' => 'KMS',
                'deskripsi' => 'Bahan pembantu kemasan, kantong, cup, dan wadah makanan untuk pesanan take away atau delivery',
            ]
        );

        // 2. Daftar Barang Kemasan (Barang Pembantu)
        $kemasans = [
            [
                'nama_barang' => 'Cup Dingin + Tutup Seal 16oz',
                'nomer_seri' => 'KMS-0001',
                'barcode' => '899100010001',
                'satuan' => 'Pcs',
                'harga_beli' => 600,
                'hpp' => 600,
                'harga_jual' => 1000,
                'tipe_barang' => 'kemasan',
                'status' => 'tersedia',
                'butuh_proses' => false,
                'bisa_dijual' => false,
            ],
            [
                'nama_barang' => 'Hot Paper Cup + Lid 8oz',
                'nomer_seri' => 'KMS-0002',
                'barcode' => '899100010002',
                'satuan' => 'Pcs',
                'harga_beli' => 800,
                'hpp' => 800,
                'harga_jual' => 1500,
                'tipe_barang' => 'kemasan',
                'status' => 'tersedia',
                'butuh_proses' => false,
                'bisa_dijual' => false,
            ],
            [
                'nama_barang' => 'Lunch Box Kraft Makanan',
                'nomer_seri' => 'KMS-0003',
                'barcode' => '899100010003',
                'satuan' => 'Pcs',
                'harga_beli' => 1500,
                'hpp' => 1500,
                'harga_jual' => 3000,
                'tipe_barang' => 'kemasan',
                'status' => 'tersedia',
                'butuh_proses' => false,
                'bisa_dijual' => false,
            ],
        ];

        foreach ($kemasans as $item) {
            $barang = Barang::updateOrCreate(
                [
                    'jenis_barang_id' => $jenis->id,
                    'nama_barang' => $item['nama_barang'],
                ],
                $item
            );

            // Inisialisasi stok 100 pcs di setiap gudang
            foreach ($allGudangs as $g) {
                $barang->gudangs()->syncWithoutDetaching([
                    $g->id => ['stok' => 100],
                ]);
            }
        }

        // 3. Pasangkan kemasan default pada menu cafe jika ada
        $cupDingin = Barang::where('nama_barang', 'Cup Dingin + Tutup Seal 16oz')->first();
        if ($cupDingin) {
            Barang::where(function ($q) {
                $q->where('nama_barang', 'ilike', '%kopi%')
                    ->orWhere('nama_barang', 'ilike', '%latte%')
                    ->orWhere('nama_barang', 'ilike', '%teh%');
            })
                ->where('tipe_barang', '!=', 'kemasan')
                ->update(['kemasan_id' => $cupDingin->id]);
        }

        $lunchBox = Barang::where('nama_barang', 'Lunch Box Kraft Makanan')->first();
        if ($lunchBox) {
            Barang::where(function ($q) {
                $q->where('nama_barang', 'ilike', '%nasi%')
                    ->orWhere('nama_barang', 'ilike', '%goreng%')
                    ->orWhere('nama_barang', 'ilike', '%mie%');
            })
                ->where('tipe_barang', '!=', 'kemasan')
                ->update(['kemasan_id' => $lunchBox->id]);
        }
    }
}
