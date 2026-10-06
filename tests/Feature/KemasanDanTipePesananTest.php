<?php

namespace Tests\Feature;

use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaMeja;
use Tests\TestCase;

class KemasanDanTipePesananTest extends TestCase
{
    use RefreshDatabase, PunyaMeja;

    public function test_kemasan_default_scope_and_single_default_guarantee()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Kemasan', 'kode_jenis' => 'KMS', 'deskripsi' => 'Kemasan']);

        $kemasan1 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Cup Dingin 16oz',
            'harga_beli' => 500,
            'harga_jual' => 1000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'status' => 'tersedia',
            'bisa_dijual' => false,
            'is_default_kemasan' => true,
        ]);

        $this->assertEquals($kemasan1->id, Barang::kemasanDefault()->first()?->id);

        $kemasan2 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Lunch Box Kraft',
            'harga_beli' => 1000,
            'harga_jual' => 2000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'status' => 'tersedia',
            'bisa_dijual' => false,
            'is_default_kemasan' => true,
        ]);

        $kemasan1->refresh();
        $this->assertFalse((bool) $kemasan1->is_default_kemasan);
        $this->assertTrue((bool) $kemasan2->is_default_kemasan);
        $this->assertEquals($kemasan2->id, Barang::kemasanDefault()->first()?->id);
    }

    public function test_kasir_endpoint_returns_kemasan_default_data()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kemasan', 'kode_jenis' => 'KMS', 'deskripsi' => 'Kemasan']);

        $kemasan = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Cup Dingin 16oz',
            'harga_beli' => 500,
            'harga_jual' => 1000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'status' => 'tersedia',
            'bisa_dijual' => false,
            'is_default_kemasan' => true,
        ]);
        $kemasan->gudangs()->attach($gudang->id, ['stok' => 100]);

        $response = $this->actingAs($user)->getJson(route('kasir.data'));

        $response->assertOk();
        $response->assertJsonPath('kemasanDefault.id', $kemasan->id);
        $response->assertJsonPath('kemasanDefault.nama_barang', 'Cup Dingin 16oz');
        $response->assertJsonPath('kemasanDefault.harga_jual', 1000);
    }

    public function test_kasir_simpan_with_delivery_and_kemasan()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $kopi = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Es Kopi Susu',
            'harga_beli' => 8000,
            'harga_jual' => 18000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);
        $kopi->gudangs()->attach($gudang->id, ['stok' => 50]);

        $kemasan = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Cup Dingin 16oz',
            'harga_beli' => 500,
            'harga_jual' => 1000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'status' => 'tersedia',
            'bisa_dijual' => false,
            'is_default_kemasan' => true,
        ]);
        $kemasan->gudangs()->attach($gudang->id, ['stok' => 100]);

        $gofood = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $payload = [
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 40000,
            'alamat_pengiriman' => 'Jl. Anggrek No. 12, RT 02/05',
            'biaya_kirim' => 10000,
            'aplikator_id' => $gofood->id,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
                [
                    'barang_id' => $kemasan->id,
                    'jumlah' => 1,
                    'satuan' => 'Pcs',
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $payload);

        $response->assertOk();

        // 18000 + 1000 = 19000 (barang) + 10000 (biaya kirim) = 29000 neto
        $penjualan = Penjualan::with('details')->first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(19000, $penjualan->total);
        $this->assertEquals(29000, $penjualan->neto);
        $this->assertEquals(10000, $penjualan->biaya_kirim);
        $this->assertEquals('Jl. Anggrek No. 12, RT 02/05', $penjualan->alamat_pengiriman);

        // Periksa DetailJual
        $this->assertCount(2, $penjualan->details);
        foreach ($penjualan->details as $d) {
            $this->assertEquals('delivery', $d->jenis_pesanan);
        }

        // Periksa endpoint riwayat mengembalikan alamat_pengiriman
        $riwayatResponse = $this->actingAs($user)->getJson(route('kasir.riwayat', ['tanggal' => $penjualan->tanggal]));
        $riwayatResponse->assertOk();
        $riwayatResponse->assertJsonPath('items.0.alamat_pengiriman', 'Jl. Anggrek No. 12, RT 02/05');
        $riwayatResponse->assertJsonPath('items.0.biaya_kirim', 10000);
    }

    public function test_tipe_barang_kemasan_can_be_default_and_used_in_pos()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kemasan', 'kode_jenis' => 'KMS', 'deskripsi' => 'Kemasan']);

        $kemasan = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Paper Bag Takeaway',
            'harga_beli' => 1000,
            'harga_jual' => 2000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'kemasan',
            'status' => 'tersedia',
            'bisa_dijual' => false,
            'is_default_kemasan' => true,
        ]);
        $kemasan->gudangs()->attach($gudang->id, ['stok' => 50]);

        // Verify scopeKemasanDefault picks up tipe_barang === 'kemasan'
        $defaultKemasan = Barang::kemasanDefault()->first();
        $this->assertNotNull($defaultKemasan);
        $this->assertEquals($kemasan->id, $defaultKemasan->id);
        $this->assertEquals('kemasan', $defaultKemasan->tipe_barang);

        // Verify kasir endpoint returns it
        $response = $this->actingAs($user)->getJson(route('kasir.data'));
        $response->assertOk();
        $response->assertJsonPath('kemasanDefault.id', $kemasan->id);
        $response->assertJsonPath('kemasanDefault.nama_barang', 'Paper Bag Takeaway');
    }

    public function test_barang_kemasan_id_relationship()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Menu Cafe', 'kode_jenis' => 'MCF', 'deskripsi' => 'Menu Cafe']);

        $kemasanCup = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Cold Cup 16oz',
            'harga_beli' => 500,
            'harga_jual' => 1000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'kemasan',
            'status' => 'tersedia',
            'bisa_dijual' => false,
        ]);

        $latte = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Iced Latte',
            'harga_beli' => 8000,
            'harga_jual' => 20000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
            'kemasan_id' => $kemasanCup->id,
        ]);

        $this->assertEquals($kemasanCup->id, $latte->kemasan?->id);
        $this->assertEquals('Cold Cup 16oz', $latte->kemasan?->nama_barang);
        $this->assertTrue($kemasanCup->barangPenggunaKemasan->contains($latte));
    }

    public function test_kasir_endpoint_returns_per_item_kemasan_data()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Menu Cafe', 'kode_jenis' => 'MCF', 'deskripsi' => 'Menu']);

        $kemasanBox = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Lunch Box Kraft Makanan',
            'harga_beli' => 1500,
            'harga_jual' => 3000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'kemasan',
            'status' => 'tersedia',
            'bisa_dijual' => false,
        ]);
        $kemasanBox->gudangs()->attach($gudang->id, ['stok' => 50]);

        $nasi = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Nasi Goreng Cafe',
            'harga_beli' => 12000,
            'harga_jual' => 25000,
            'satuan' => 'Porsi',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
            'kemasan_id' => $kemasanBox->id,
        ]);
        $nasi->gudangs()->attach($gudang->id, ['stok' => 20]);

        $response = $this->actingAs($user)->getJson(route('kasir.data'));
        $response->assertOk();

        $barangList = collect($response->json('barang'));
        $nasiData = $barangList->firstWhere('id', $nasi->id);

        $this->assertNotNull($nasiData);
        $this->assertEquals($kemasanBox->id, $nasiData['kemasan_id']);
        $this->assertEquals('Lunch Box Kraft Makanan', $nasiData['kemasan']['nama_barang']);
        $this->assertEquals(3000, $nasiData['kemasan']['harga_jual']);
    }
}
