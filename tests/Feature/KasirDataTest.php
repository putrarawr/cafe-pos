<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_page_injects_tier_pricing_data()
    {
        $user = User::factory()->create();
        $jenis = JenisBarang::create(['nama_jenis' => 'Rokok', 'kode_jenis' => 'ROK', 'deskripsi' => 'Rokok']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Garam Gudang Filter 12',
            'harga_beli' => 22000,
            'harga_jual' => 24000,
            'satuan' => 'Pack',
            'tipe_harga_bertingkat' => 'persen',
            'min_qty_1' => 5,
            'nilai_tier_1' => 5,
            'min_qty_2' => 10,
            'nilai_tier_2' => 7,
            'min_qty_3' => 20,
            'nilai_tier_3' => 8,
        ]);

        $response = $this->actingAs($user)->get(route('kasir'));

        $response->assertOk();
        $response->assertSee('Garam Gudang Filter 12');
        $response->assertSee('"min_qty_1":5', false);
        $response->assertSee('"nilai_tier_1":5', false);

        // Uji kalkulasi getHargaTierForQty
        $this->assertEquals(24000, $barang->getHargaTierForQty(1));
        $this->assertEquals(22800, $barang->getHargaTierForQty(5)); // 24000 - 5% = 22800
        $this->assertEquals(22320, $barang->getHargaTierForQty(10)); // 24000 - 7% = 22320
        $this->assertEquals(22080, $barang->getHargaTierForQty(20)); // 24000 - 8% = 22080
    }
}
