<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed 5 Gudang
        $gudangs = [
            ['nama_gudang' => 'Gudang Utama (Pusat)', 'alamat' => 'Jl. Raya Industri No. 12, Surabaya'],
            ['nama_gudang' => 'Gudang Cabang Barat', 'alamat' => 'Jl. Darmo Permai No. 45, Surabaya'],
            ['nama_gudang' => 'Gudang Transit Logistik', 'alamat' => 'Jl. Ahmad Yani No. 88, Sidoarjo'],
            ['nama_gudang' => 'Gudang Retur & Garansi', 'alamat' => 'Jl. Gatot Subroto No. 15, Malang'],
            ['nama_gudang' => 'Gudang Depo Timur', 'alamat' => 'Jl. Pemuda No. 30, Pasuruan'],
        ];

        foreach ($gudangs as $g) {
            Gudang::firstOrCreate(['nama_gudang' => $g['nama_gudang']], $g);
        }

        // 2. Seed 5 Supplier
        $suppliers = [
            ['nama_supplier' => 'PT. Gudang Garam Tbk', 'no_telepon' => '081234567801', 'alamat' => 'Kediri, Jawa Timur', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Indofood Sukses Makmur', 'no_telepon' => '081234567802', 'alamat' => 'Jakarta Selatan', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Mayora Indah Tbk', 'no_telepon' => '081234567803', 'alamat' => 'Tangerang, Banten', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Unilever Indonesia Tbk', 'no_telepon' => '081234567804', 'alamat' => 'Cikarang, Bekasi', 'status' => 'aktif'],
            ['nama_supplier' => 'CV. Sumber Makmur Sejahtera', 'no_telepon' => '081234567805', 'alamat' => 'Surabaya, Jawa Timur', 'status' => 'aktif'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['nama_supplier' => $s['nama_supplier']], $s);
        }

        // 3. Seed 5 Kategori / Jenis Barang & Master Produk Lengkap
        $categories = [
            [
                'nama_jenis' => 'Rokok',
                'kode_jenis' => 'ROK',
                'deskripsi' => 'Segala jenis produk rokok kretek dan filter',
                'items' => [
                    [
                        'nama_barang' => 'Garam Gudang Filter 12',
                        'nomer_seri' => 'ROK-0001',
                        'barcode' => '899990900101',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 22000,
                        'hpp' => 22000,
                        'harga_jual' => 24000,
                        'satuan_2' => 'Pres/Slof',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 218000,
                        'harga_jual_2' => 238000,
                        'satuan_3' => 'Bal',
                        'isi_satuan_3' => 200,
                        'harga_beli_3' => 4300000,
                        'harga_jual_3' => 4700000,
                        'satuan_4' => 'Karton',
                        'isi_satuan_4' => 800,
                        'harga_beli_4' => 17000000,
                        'harga_jual_4' => 18600000,
                        'tipe_harga_bertingkat' => 'persen',
                        'min_qty_1' => 10, 'nilai_tier_1' => 2.0,
                        'min_qty_2' => 50, 'nilai_tier_2' => 4.0,
                        'min_qty_3' => 200, 'nilai_tier_3' => 6.0,
                    ],
                    [
                        'nama_barang' => 'Sampoerna Mild 16',
                        'nomer_seri' => 'ROK-0002',
                        'barcode' => '899990900102',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 28000,
                        'hpp' => 28000,
                        'harga_jual' => 31000,
                        'satuan_2' => 'Pres/Slof',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 278000,
                        'harga_jual_2' => 305000,
                        'satuan_3' => 'Bal',
                        'isi_satuan_3' => 200,
                        'harga_beli_3' => 5500000,
                        'harga_jual_3' => 6000000,
                        'tipe_harga_bertingkat' => 'persen',
                        'min_qty_1' => 10, 'nilai_tier_1' => 2.0,
                        'min_qty_2' => 50, 'nilai_tier_2' => 5.0,
                    ],
                    [
                        'nama_barang' => 'Djarum Super 12',
                        'nomer_seri' => 'ROK-0003',
                        'barcode' => '899990900103',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 21000,
                        'hpp' => 21000,
                        'harga_jual' => 23500,
                        'satuan_2' => 'Pres/Slof',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 208000,
                        'harga_jual_2' => 230000,
                        'satuan_3' => 'Bal',
                        'isi_satuan_3' => 200,
                        'harga_beli_3' => 4100000,
                        'harga_jual_3' => 4500000,
                        'tipe_harga_bertingkat' => 'nominal',
                        'min_qty_1' => 10, 'nilai_tier_1' => 23000,
                        'min_qty_2' => 50, 'nilai_tier_2' => 22500,
                    ],
                    [
                        'nama_barang' => 'Marlboro Red 20',
                        'nomer_seri' => 'ROK-0004',
                        'barcode' => '899990900104',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 38000,
                        'hpp' => 38000,
                        'harga_jual' => 42000,
                        'satuan_2' => 'Pres/Slof',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 375000,
                        'harga_jual_2' => 410000,
                    ],
                    [
                        'nama_barang' => 'Surya Professional 16',
                        'nomer_seri' => 'ROK-0005',
                        'barcode' => '899990900105',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 27000,
                        'hpp' => 27000,
                        'harga_jual' => 30000,
                        'satuan_2' => 'Pres/Slof',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 268000,
                        'harga_jual_2' => 295000,
                    ],
                ],
            ],
            [
                'nama_jenis' => 'Minuman Kemasan',
                'kode_jenis' => 'MNM',
                'deskripsi' => 'Minuman bersoda, jus, dan air mineral kemasan',
                'items' => [
                    [
                        'nama_barang' => 'Teh Botol Sosro 450ml',
                        'nomer_seri' => 'MNM-0001',
                        'barcode' => '899990900201',
                        'satuan' => 'Botol',
                        'harga_beli' => 4000,
                        'hpp' => 4000,
                        'harga_jual' => 5500,
                        'satuan_2' => 'Karton',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 95000,
                        'harga_jual_2' => 125000,
                        'tipe_harga_bertingkat' => 'persen',
                        'min_qty_1' => 12, 'nilai_tier_1' => 5.0,
                        'min_qty_2' => 24, 'nilai_tier_2' => 10.0,
                    ],
                    [
                        'nama_barang' => 'Le Minerale 600ml',
                        'nomer_seri' => 'MNM-0002',
                        'barcode' => '899990900202',
                        'satuan' => 'Botol',
                        'harga_beli' => 2500,
                        'hpp' => 2500,
                        'harga_jual' => 3500,
                        'satuan_2' => 'Karton',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 58000,
                        'harga_jual_2' => 78000,
                    ],
                    [
                        'nama_barang' => 'Coca Cola 390ml',
                        'nomer_seri' => 'MNM-0003',
                        'barcode' => '899990900203',
                        'satuan' => 'Botol',
                        'harga_beli' => 4500,
                        'hpp' => 4500,
                        'harga_jual' => 6000,
                        'satuan_2' => 'Karton',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 105000,
                        'harga_jual_2' => 138000,
                    ],
                    [
                        'nama_barang' => 'Pocari Sweat 500ml',
                        'nomer_seri' => 'MNM-0004',
                        'barcode' => '899990900204',
                        'satuan' => 'Botol',
                        'harga_beli' => 6000,
                        'hpp' => 6000,
                        'harga_jual' => 8000,
                        'satuan_2' => 'Karton',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 140000,
                        'harga_jual_2' => 185000,
                    ],
                    [
                        'nama_barang' => 'Ultra Milk Cokelat 250ml',
                        'nomer_seri' => 'MNM-0005',
                        'barcode' => '899990900205',
                        'satuan' => 'Kotak',
                        'harga_beli' => 5500,
                        'hpp' => 5500,
                        'harga_jual' => 7000,
                        'satuan_2' => 'Karton',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 130000,
                        'harga_jual_2' => 162000,
                    ],
                ],
            ],
            [
                'nama_jenis' => 'Makanan Ringan',
                'kode_jenis' => 'MKN',
                'deskripsi' => 'Snack, biskuit, mie instan, dan makanan kemasan',
                'items' => [
                    [
                        'nama_barang' => 'Indomie Goreng Original 85g',
                        'nomer_seri' => 'MKN-0001',
                        'barcode' => '899990900301',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 2800,
                        'hpp' => 2800,
                        'harga_jual' => 3500,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 40,
                        'harga_beli_2' => 110000,
                        'harga_jual_2' => 135000,
                        'satuan_3' => 'Karton Besar',
                        'isi_satuan_3' => 200,
                        'harga_beli_3' => 540000,
                        'harga_jual_3' => 660000,
                        'tipe_harga_bertingkat' => 'persen',
                        'min_qty_1' => 10, 'nilai_tier_1' => 3.0,
                        'min_qty_2' => 40, 'nilai_tier_2' => 8.0,
                    ],
                    [
                        'nama_barang' => 'Chitato Sapi Panggang 68g',
                        'nomer_seri' => 'MKN-0002',
                        'barcode' => '899990900302',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 9500,
                        'hpp' => 9500,
                        'harga_jual' => 11500,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 30,
                        'harga_beli_2' => 280000,
                        'harga_jual_2' => 335000,
                    ],
                    [
                        'nama_barang' => 'Oreo Original 119.6g',
                        'nomer_seri' => 'MKN-0003',
                        'barcode' => '899990900303',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 8000,
                        'hpp' => 8000,
                        'harga_jual' => 10000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 188000,
                        'harga_jual_2' => 230000,
                    ],
                    [
                        'nama_barang' => 'Silverqueen Milk Chocolate 58g',
                        'nomer_seri' => 'MKN-0004',
                        'barcode' => '899990900304',
                        'satuan' => 'Batang',
                        'harga_beli' => 13500,
                        'hpp' => 13500,
                        'harga_jual' => 16500,
                        'satuan_2' => 'Box',
                        'isi_satuan_2' => 10,
                        'harga_beli_2' => 132000,
                        'harga_jual_2' => 160000,
                    ],
                    [
                        'nama_barang' => 'Taro Net Rumput Laut 36g',
                        'nomer_seri' => 'MKN-0005',
                        'barcode' => '899990900305',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 4000,
                        'hpp' => 4000,
                        'harga_jual' => 5000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 40,
                        'harga_beli_2' => 155000,
                        'harga_jual_2' => 190000,
                    ],
                ],
            ],
            [
                'nama_jenis' => 'Bumbu & Sembako',
                'kode_jenis' => 'SMB',
                'deskripsi' => 'Bumbu dapur, minyak goreng, gula, dan beras',
                'items' => [
                    [
                        'nama_barang' => 'Minyak Goreng Bimoli 2 Liter',
                        'nomer_seri' => 'SMB-0001',
                        'barcode' => '899990900401',
                        'satuan' => 'Pouch',
                        'harga_beli' => 34000,
                        'hpp' => 34000,
                        'harga_jual' => 38000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 6,
                        'harga_beli_2' => 200000,
                        'harga_jual_2' => 224000,
                    ],
                    [
                        'nama_barang' => 'Gula Pasir Gulaku 1kg',
                        'nomer_seri' => 'SMB-0002',
                        'barcode' => '899990900402',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 15500,
                        'hpp' => 15500,
                        'harga_jual' => 17500,
                        'satuan_2' => 'Karung/Bal',
                        'isi_satuan_2' => 20,
                        'harga_beli_2' => 305000,
                        'harga_jual_2' => 345000,
                    ],
                    [
                        'nama_barang' => 'Beras Setra Ramos 5kg',
                        'nomer_seri' => 'SMB-0003',
                        'barcode' => '899990900403',
                        'satuan' => 'Karung',
                        'harga_beli' => 68000,
                        'hpp' => 68000,
                        'harga_jual' => 75000,
                    ],
                    [
                        'nama_barang' => 'Garam Cap Kapal 500g',
                        'nomer_seri' => 'SMB-0004',
                        'barcode' => '899990900404',
                        'satuan' => 'Bungkus',
                        'harga_beli' => 3000,
                        'hpp' => 3000,
                        'harga_jual' => 4000,
                        'satuan_2' => 'Pack',
                        'isi_satuan_2' => 20,
                        'harga_beli_2' => 58000,
                        'harga_jual_2' => 76000,
                    ],
                    [
                        'nama_barang' => 'Kecap Manis Bango 520ml',
                        'nomer_seri' => 'SMB-0005',
                        'barcode' => '899990900405',
                        'satuan' => 'Pouch',
                        'harga_beli' => 21000,
                        'hpp' => 21000,
                        'harga_jual' => 24500,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 12,
                        'harga_beli_2' => 248000,
                        'harga_jual_2' => 288000,
                    ],
                ],
            ],
            [
                'nama_jenis' => 'Perawatan Pribadi',
                'kode_jenis' => 'PRW',
                'deskripsi' => 'Sabun, sampo, pasta gigi, dan perlengkapan mandi',
                'items' => [
                    [
                        'nama_barang' => 'Sabun Lifebuoy Red 110g',
                        'nomer_seri' => 'PRW-0001',
                        'barcode' => '899990900501',
                        'satuan' => 'Batang',
                        'harga_beli' => 4000,
                        'hpp' => 4000,
                        'harga_jual' => 5000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 48,
                        'harga_beli_2' => 188000,
                        'harga_jual_2' => 235000,
                    ],
                    [
                        'nama_barang' => 'Sampo Sunsilk Black 160ml',
                        'nomer_seri' => 'PRW-0002',
                        'barcode' => '899990900502',
                        'satuan' => 'Botol',
                        'harga_beli' => 18000,
                        'hpp' => 18000,
                        'harga_jual' => 21000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 425000,
                        'harga_jual_2' => 495000,
                    ],
                    [
                        'nama_barang' => 'Pasta Gigi Pepsodent 190g',
                        'nomer_seri' => 'PRW-0003',
                        'barcode' => '899990900503',
                        'satuan' => 'Tube',
                        'harga_beli' => 11000,
                        'hpp' => 11000,
                        'harga_jual' => 13500,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 36,
                        'harga_beli_2' => 390000,
                        'harga_jual_2' => 475000,
                    ],
                    [
                        'nama_barang' => 'Sabun Cuci Tangan Dettol 245ml',
                        'nomer_seri' => 'PRW-0004',
                        'barcode' => '899990900504',
                        'satuan' => 'Botol',
                        'harga_beli' => 22000,
                        'hpp' => 22000,
                        'harga_jual' => 26000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 12,
                        'harga_beli_2' => 258000,
                        'harga_jual_2' => 305000,
                    ],
                    [
                        'nama_barang' => 'Tisu Paseo 250 Sheets',
                        'nomer_seri' => 'PRW-0005',
                        'barcode' => '899990900505',
                        'satuan' => 'Pack',
                        'harga_beli' => 14000,
                        'hpp' => 14000,
                        'harga_jual' => 17000,
                        'satuan_2' => 'Dus',
                        'isi_satuan_2' => 24,
                        'harga_beli_2' => 330000,
                        'harga_jual_2' => 400000,
                    ],
                ],
            ],
        ];

        $allGudangs = Gudang::all();

        foreach ($categories as $cat) {
            $jenis = JenisBarang::firstOrCreate(
                ['nama_jenis' => $cat['nama_jenis']],
                [
                    'kode_jenis' => $cat['kode_jenis'],
                    'deskripsi' => $cat['deskripsi'],
                ]
            );

            foreach ($cat['items'] as $itemData) {
                $barang = Barang::updateOrCreate(
                    [
                        'jenis_barang_id' => $jenis->id,
                        'nama_barang' => $itemData['nama_barang'],
                    ],
                    $itemData
                );

                // Default inisialisasi stok 50 di setiap gudang
                foreach ($allGudangs as $g) {
                    $barang->gudangs()->syncWithoutDetaching([$g->id => ['stok' => 50]]);
                }
            }
        }
    }
}
