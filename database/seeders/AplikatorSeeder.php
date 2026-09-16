<?php

namespace Database\Seeders;

use App\Models\Aplikator;
use Illuminate\Database\Seeder;

class AplikatorSeeder extends Seeder
{
    public function run(): void
    {
        $aplikators = [
            [
                'nama_aplikator' => 'GoFood',
                'kode_aplikator' => 'GOFOOD',
                'persentase_komisi' => 20.00,
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery GoFood (Gojek)',
            ],
            [
                'nama_aplikator' => 'GrabFood',
                'kode_aplikator' => 'GRABFOOD',
                'persentase_komisi' => 20.00,
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery GrabFood (Grab)',
            ],
            [
                'nama_aplikator' => 'ShopeeFood',
                'kode_aplikator' => 'SHOPEEFOOD',
                'persentase_komisi' => 20.00,
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery ShopeeFood (Shopee)',
            ],
            [
                'nama_aplikator' => 'Maxim Food',
                'kode_aplikator' => 'MAXIM',
                'persentase_komisi' => 20.00,
                'status_aktif' => true,
                'keterangan' => 'Aplikator delivery Maxim Foods & Goods',
            ],
            [
                'nama_aplikator' => 'Kurir Toko / Internal',
                'kode_aplikator' => 'TOKO',
                'persentase_komisi' => 0.00,
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
