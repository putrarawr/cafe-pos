<?php

namespace Database\Seeders;

use App\Models\Aplikator;
use Illuminate\Database\Seeder;

class AplikatorSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan direktori storage/app/public/aplikator tersedia
        $storageAplikatorDir = storage_path('app/public/aplikator');
        if (! is_dir($storageAplikatorDir)) {
            mkdir($storageAplikatorDir, 0755, true);
        }

        // Salin logo bawaan jika ada di public/img/aplikator
        $sourceDir = public_path('img/aplikator');
        if (is_dir($sourceDir)) {
            foreach (['gofood.svg', 'grabfood.png', 'shopeefood.png', 'maxim.png'] as $file) {
                $src = "{$sourceDir}/{$file}";
                $dest = "{$storageAplikatorDir}/{$file}";
                if (file_exists($src) && ! file_exists($dest)) {
                    copy($src, $dest);
                }
            }
        }

        $aplikators = [
            [
                'nama_aplikator' => 'GoFood',
                'kode_aplikator' => 'GOFOOD',
                'persentase_komisi' => 20.00,
                'gambar' => 'aplikator/gofood.svg',
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery GoFood (Gojek)',
            ],
            [
                'nama_aplikator' => 'GrabFood',
                'kode_aplikator' => 'GRABFOOD',
                'persentase_komisi' => 20.00,
                'gambar' => 'aplikator/grabfood.png',
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery GrabFood (Grab)',
            ],
            [
                'nama_aplikator' => 'ShopeeFood',
                'kode_aplikator' => 'SHOPEEFOOD',
                'persentase_komisi' => 20.00,
                'gambar' => 'aplikator/shopeefood.png',
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery ShopeeFood (Shopee)',
            ],
            [
                'nama_aplikator' => 'Maxim Food',
                'kode_aplikator' => 'MAXIM',
                'persentase_komisi' => 20.00,
                'gambar' => 'aplikator/maxim.png',
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery Maxim Foods & Goods',
            ],
            [
                'nama_aplikator' => 'Kurir Toko / Internal',
                'kode_aplikator' => 'TOKO',
                'persentase_komisi' => 0.00,
                'gambar' => null,
                'status_aktif' => true,
                'keterangan' => 'Pengantaran delivery kurir internal toko',
            ],
        ];

        foreach ($aplikators as $item) {
            Aplikator::updateOrCreate(
                ['kode_aplikator' => $item['kode_aplikator']],
                $item
            );
        }
    }
}
