<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HargaBertingkatTest extends TestCase
{
    use RefreshDatabase;

    public function test_perhitungan_harga_tier_persen()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Saset',
            'harga_beli' => 1000,
            'harga_jual' => 2000,
            'satuan' => 'Pcs',
            'tipe_harga_bertingkat' => 'persen',
            'min_qty_1' => 1,
            'nilai_tier_1' => 0,
            'min_qty_2' => 6,
            'nilai_tier_2' => 10, // diskon 10% -> 1800
            'min_qty_3' => 20,
            'nilai_tier_3' => 20, // diskon 20% -> 1600
        ]);

        $this->assertEquals(2000, $barang->getHargaTierForQty(1));
        $this->assertEquals(2000, $barang->getHargaTierForQty(5));
        $this->assertEquals(1800, $barang->getHargaTierForQty(6));
        $this->assertEquals(1800, $barang->getHargaTierForQty(10));
        $this->assertEquals(1600, $barang->getHargaTierForQty(20));
    }

    public function test_perhitungan_harga_tier_nominal()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Air Mineral',
            'harga_beli' => 2000,
            'harga_jual' => 3500,
            'satuan' => 'Botol',
            'tipe_harga_bertingkat' => 'nominal',
            'min_qty_1' => 1,
            'nilai_tier_1' => 3500,
            'min_qty_2' => 5,
            'nilai_tier_2' => 3000,
            'min_qty_3' => 10,
            'nilai_tier_3' => 2500,
        ]);

        $this->assertEquals(3500, $barang->getHargaTierForQty(1));
        $this->assertEquals(3000, $barang->getHargaTierForQty(5));
        $this->assertEquals(2500, $barang->getHargaTierForQty(12));
    }

    public function test_kasir_menggunakan_harga_tier_saat_transaksi()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Snack', 'kode_jenis' => 'SNK', 'deskripsi' => 'Snack']);
        
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Chiki',
            'harga_beli' => 4000,
            'harga_jual' => 5000,
            'satuan' => 'Pcs',
            'tipe_harga_bertingkat' => 'persen',
            'min_qty_1' => 1,
            'nilai_tier_1' => 0,
            'min_qty_2' => 10,
            'nilai_tier_2' => 10, // harga 4500 per pcs
        ]);

        $barang->gudangs()->attach($gudang->id, ['stok' => 50]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 50000,
            'details' => [
                [
                    'barang_id' => $barang->id,
                    'jumlah' => 10,
                    'diskon' => 0,
                    'satuan' => 'Pcs',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('penjualan', [
            'total' => 45000, // 10 pcs * 4500
        ]);
    }

    public function test_detail_jual_mencatat_potongan_harga_tier()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Snack', 'kode_jenis' => 'SNK', 'deskripsi' => 'Snack']);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Chiki',
            'harga_beli' => 4000,
            'harga_jual' => 5000,
            'satuan' => 'Pcs',
            'tipe_harga_bertingkat' => 'persen',
            'min_qty_1' => 1,
            'nilai_tier_1' => 0,
            'min_qty_2' => 10,
            'nilai_tier_2' => 10, // harga 4500 per pcs
        ]);

        $barang->gudangs()->attach($gudang->id, ['stok' => 50]);

        $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 50000,
            'details' => [
                [
                    'barang_id' => $barang->id,
                    'jumlah' => 10,
                    'diskon' => 0,
                    'satuan' => 'Pcs',
                ],
            ],
        ])->assertOk();

        // harga normal disimpan, potongan dari harga bertingkat dicatat di detail_jual
        $this->assertDatabaseHas('detail_jual', [
            'harga' => 5000,   // harga normal per satuan
            'diskon' => 5000,  // potongan tier: 10 pcs x (5000 - 4500)
            'subtotal' => 45000,
        ]);

        $this->assertDatabaseHas('penjualan', [
            'total' => 45000,
        ]);
    }
}
