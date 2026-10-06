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

class PilihanAplikatorDeliveryTest extends TestCase
{
    use RefreshDatabase, PunyaMeja;

    private function setupKafe(): array
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

        $gofood = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $grabfood = Aplikator::create([
            'nama_aplikator' => 'GrabFood',
            'kode_aplikator' => 'GRABFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        return [$user, $gudang, $kopi, $gofood, $grabfood];
    }

    public function test_kasir_data_mengembalikan_aplikator_aktif_saja()
    {
        [$user] = $this->setupKafe();

        Aplikator::create([
            'nama_aplikator' => 'Aplikator Nonaktif',
            'kode_aplikator' => 'OFF',
            'persentase_komisi' => 10,
            'status_aktif' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('kasir.data'));

        $response->assertOk();

        $aplikators = collect($response->json('aplikator'));
        $this->assertTrue($aplikators->contains('nama_aplikator', 'GoFood'));
        $this->assertTrue($aplikators->contains('nama_aplikator', 'GrabFood'));
        $this->assertFalse($aplikators->contains('nama_aplikator', 'Aplikator Nonaktif'));
    }

    public function test_kasir_simpan_delivery_tanpa_aplikator_ditolak()
    {
        [$user, $gudang, $kopi] = $this->setupKafe();

        $payload = [
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000,
            'alamat_pengiriman' => 'Jl. Melati No. 5',
            'biaya_kirim' => 5000,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $payload);

        $response->assertStatus(422);
        $this->assertSame(0, Penjualan::count());
    }

    public function test_kasir_simpan_delivery_dengan_aplikator_tersimpan()
    {
        [$user, $gudang, $kopi, , $grabfood] = $this->setupKafe();

        $payload = [
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 25000,
            'alamat_pengiriman' => 'Jl. Anggrek No. 12',
            'biaya_kirim' => 5000,
            'aplikator_id' => $grabfood->id,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $payload);

        $response->assertOk();

        $penjualan = Penjualan::with('aplikator')->first();
        $this->assertNotNull($penjualan);
        $this->assertEquals($grabfood->id, $penjualan->aplikator_id);
        $this->assertEquals('GrabFood', $penjualan->aplikator->nama_aplikator);
        $this->assertEquals(23000, $penjualan->neto); // 18000 + 5000 kirim

        $riwayat = $this->actingAs($user)->getJson(route('kasir.riwayat', ['tanggal' => $penjualan->tanggal]));
        $riwayat->assertOk();
        $riwayat->assertJsonPath('items.0.aplikator', 'GrabFood');
        $riwayat->assertJsonPath('items.0.biaya_kirim', 5000);
    }

    public function test_kasir_simpan_dine_in_tanpa_aplikator_tetap_sukses()
    {
        [$user, $gudang, $kopi] = $this->setupKafe();

        $payload = $this->denganMeja([
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 18000,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'dine_in',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $payload);

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertNull($penjualan->aplikator_id);
    }

    public function test_kasir_simpan_abaikan_aplikator_untuk_non_delivery()
    {
        [$user, $gudang, $kopi, $gofood] = $this->setupKafe();

        $payload = [
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 18000,
            'aplikator_id' => $gofood->id,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $payload);

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNull($penjualan->aplikator_id);
    }
}