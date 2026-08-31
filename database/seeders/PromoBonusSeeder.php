<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\PromoBonus;
use Illuminate\Database\Seeder;

class PromoBonusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $indomie = Barang::where('nama_barang', 'like', '%Indomie Goreng%')->first();
        $tehBotol = Barang::where('nama_barang', 'like', '%Teh Botol Sosro%')->first();
        $chitato = Barang::where('nama_barang', 'like', '%Chitato%')->first();
        $oreo = Barang::where('nama_barang', 'like', '%Oreo%')->first();
        $bimoli = Barang::where('nama_barang', 'like', '%Bimoli%')->first();
        $gulaku = Barang::where('nama_barang', 'like', '%Gulaku%')->first();

        $promos = [
            [
                'nama_promo' => 'Beli 10 Indomie Gratis 1 Teh Botol',
                'barang_utama_id' => $indomie?->id,
                'min_qty_utama' => 10,
                'satuan_utama' => 'Bungkus',
                'barang_bonus_id' => $tehBotol?->id,
                'qty_bonus' => 1,
                'satuan_bonus' => 'Botol',
                'is_kelipatan' => true,
                'is_aktif' => true,
                'tanggal_mulai' => now()->subDays(30)->toDateString(),
                'tanggal_selesai' => now()->addYear()->toDateString(),
            ],
            [
                'nama_promo' => 'Beli 5 Teh Botol Gratis 1 Teh Botol',
                'barang_utama_id' => $tehBotol?->id,
                'min_qty_utama' => 5,
                'satuan_utama' => 'Botol',
                'barang_bonus_id' => $tehBotol?->id,
                'qty_bonus' => 1,
                'satuan_bonus' => 'Botol',
                'is_kelipatan' => true,
                'is_aktif' => true,
                'tanggal_mulai' => now()->subDays(30)->toDateString(),
                'tanggal_selesai' => now()->addYear()->toDateString(),
            ],
            [
                'nama_promo' => 'Beli 6 Chitato Gratis 1 Oreo',
                'barang_utama_id' => $chitato?->id,
                'min_qty_utama' => 6,
                'satuan_utama' => 'Bungkus',
                'barang_bonus_id' => $oreo?->id,
                'qty_bonus' => 1,
                'satuan_bonus' => 'Bungkus',
                'is_kelipatan' => true,
                'is_aktif' => true,
                'tanggal_mulai' => now()->subDays(30)->toDateString(),
                'tanggal_selesai' => now()->addYear()->toDateString(),
            ],
            [
                'nama_promo' => 'Beli 5 Bimoli Gratis 1 Gula Pasir',
                'barang_utama_id' => $bimoli?->id,
                'min_qty_utama' => 5,
                'satuan_utama' => 'Pouch',
                'barang_bonus_id' => $gulaku?->id,
                'qty_bonus' => 1,
                'satuan_bonus' => 'Bungkus',
                'is_kelipatan' => true,
                'is_aktif' => true,
                'tanggal_mulai' => now()->subDays(30)->toDateString(),
                'tanggal_selesai' => now()->addYear()->toDateString(),
            ],
        ];

        foreach ($promos as $promo) {
            if ($promo['barang_utama_id'] && $promo['barang_bonus_id']) {
                PromoBonus::updateOrCreate(
                    ['nama_promo' => $promo['nama_promo']],
                    $promo
                );
            }
        }
    }
}
