<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\PromoBonus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_active_filtering()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);
        $b1 = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Indomie', 'harga_beli' => 2500, 'harga_jual' => 3000, 'satuan' => 'Pcs']);
        $b2 = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Teh Botol', 'harga_beli' => 3000, 'harga_jual' => 4000, 'satuan' => 'Pcs']);

        // Promo 1: Aktif tanpa batas tanggal
        $promo1 = PromoBonus::create([
            'nama_promo' => 'Beli Indomie Gratis Teh Botol',
            'barang_utama_id' => $b1->id,
            'min_qty_utama' => 5,
            'satuan_utama' => 'Pcs',
            'barang_bonus_id' => $b2->id,
            'qty_bonus' => 1,
            'satuan_bonus' => 'Pcs',
            'is_kelipatan' => true,
            'is_aktif' => true,
        ]);

        // Promo 2: Non-aktif
        PromoBonus::create([
            'nama_promo' => 'Promo Kadaluarsa',
            'barang_utama_id' => $b1->id,
            'min_qty_utama' => 5,
            'satuan_utama' => 'Pcs',
            'barang_bonus_id' => $b2->id,
            'qty_bonus' => 1,
            'satuan_bonus' => 'Pcs',
            'is_kelipatan' => true,
            'is_aktif' => false,
        ]);

        $activePromos = PromoBonus::active()->get();
        $this->assertCount(1, $activePromos);
        $this->assertEquals($promo1->id, $activePromos->first()->id);
    }

    public function test_simpan_transaksi_dengan_barang_bonus()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Snack', 'kode_jenis' => 'SNK', 'deskripsi' => 'Snack']);

        $bUtama = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Kopi', 'harga_beli' => 2000, 'harga_jual' => 3000, 'satuan' => 'Pcs']);
        $bBonus = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Gula', 'harga_beli' => 1000, 'harga_jual' => 1500, 'satuan' => 'Pcs']);

        $bUtama->gudangs()->attach($gudang->id, ['stok' => 50]);
        $bBonus->gudangs()->attach($gudang->id, ['stok' => 50]);

        $promo = PromoBonus::create([
            'nama_promo' => 'Beli 5 Kopi Gratis 1 Gula',
            'barang_utama_id' => $bUtama->id,
            'min_qty_utama' => 5,
            'satuan_utama' => 'Pcs',
            'barang_bonus_id' => $bBonus->id,
            'qty_bonus' => 1,
            'satuan_bonus' => 'Pcs',
            'is_kelipatan' => true,
            'is_aktif' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 30000,
            'details' => [
                [
                    'barang_id' => $bUtama->id,
                    'jumlah' => 10,
                    'diskon' => 0,
                    'satuan' => 'Pcs',
                    'is_bonus' => false,
                    'promo_id' => $promo->id,
                ],
                [
                    'barang_id' => $bBonus->id,
                    'jumlah' => 2,
                    'diskon' => 0,
                    'satuan' => 'Pcs',
                    'is_bonus' => true,
                ],
            ],
        ]);

        $response->assertOk();

        // Total penjualan harus 30000 (hanya 10 kopi * 3000, bonus gula = 0)
        $this->assertDatabaseHas('penjualan', [
            'total' => 30000,
            'neto' => 30000,
        ]);

        // Detail jual untuk bonus harus memiliki harga 0 dan ter-tandai bonus
        $this->assertDatabaseHas('detail_jual', [
            'barang_id' => $bBonus->id,
            'jumlah' => 2,
            'harga' => 0,
            'subtotal' => 0,
            'is_bonus' => true,
        ]);

        // Item utama harus menyimpan data bonus
        $this->assertDatabaseHas('detail_jual', [
            'barang_id' => $bUtama->id,
            'is_bonus' => false,
            'bonus_barang_id' => $bBonus->id,
            'bonus_qty' => 2,
        ]);
    }
}
