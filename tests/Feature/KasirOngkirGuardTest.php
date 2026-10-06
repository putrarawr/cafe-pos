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

class KasirOngkirGuardTest extends TestCase
{
    use RefreshDatabase, PunyaMeja;

    private function buatUserDanGudang(): array
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        return [$user, $gudang];
    }

    private function buatBarangKopi(Gudang $gudang): Barang
    {
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
        return $kopi;
    }

    private function buatAplikatorGoFood(): Aplikator
    {
        return Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);
    }

    public function test_dine_in_mengabaikan_ongkir_yang_terkirim()
    {
        [$user, $gudang] = $this->buatUserDanGudang();
        $kopi = $this->buatBarangKopi($gudang);

        // dine_in → meja wajib dipilih, sisanya (take_away/delivery) tidak.
        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $this->denganMeja([
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000,
            'alamat_pengiriman' => 'Jl. Lama yang Tersisa',
            'biaya_kirim' => 5000,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'dine_in',
                ],
            ],
        ]));

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(18000, $penjualan->neto, 'Neto dine_in tidak boleh memuat ongkir.');
        $this->assertEquals(0, $penjualan->biaya_kirim, 'Ongkir harus diabaikan pada transaksi non-antar.');
        $this->assertNull($penjualan->alamat_pengiriman, 'Alamat pengiriman harus diabaikan pada transaksi non-antar.');
        $this->assertNull($penjualan->aplikator_id);
    }

    public function test_take_away_tetap_menyimpan_ongkir()
    {
        [$user, $gudang] = $this->buatUserDanGudang();
        $kopi = $this->buatBarangKopi($gudang);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 23000,
            'alamat_pengiriman' => 'Jl. Ambil Sendiri',
            'biaya_kirim' => 5000,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ],
        ]);

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(23000, $penjualan->neto);
        $this->assertEquals(5000, $penjualan->biaya_kirim);
        $this->assertEquals('Jl. Ambil Sendiri', $penjualan->alamat_pengiriman);

        // Rantai riwayat (sumber label struk) harus membawa ongkir & jenis per item
        $riwayat = $this->actingAs($user)->getJson(route('kasir.riwayat', ['tanggal' => $penjualan->tanggal]));
        $riwayat->assertOk();
        $riwayat->assertJsonPath('items.0.biaya_kirim', 5000);
        $riwayat->assertJsonPath('items.0.details.0.jenis_pesanan', 'take_away');
    }

    public function test_delivery_tetap_menyimpan_ongkir_dan_komisi()
    {
        [$user, $gudang] = $this->buatUserDanGudang();
        $kopi = $this->buatBarangKopi($gudang);
        $gofood = $this->buatAplikatorGoFood();

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 23000,
            'alamat_pengiriman' => 'Jl. Delivery No. 7',
            'biaya_kirim' => 5000,
            'aplikator_id' => $gofood->id,
            'details' => [
                [
                    'barang_id' => $kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
            ],
        ]);

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(23000, $penjualan->neto);
        $this->assertEquals(5000, $penjualan->biaya_kirim);
        $this->assertEquals('Jl. Delivery No. 7', $penjualan->alamat_pengiriman);
        $this->assertEquals($gofood->id, $penjualan->aplikator_id);
        // Komisi = 20% dari neto SEBELUM ongkir (18000) = 3600
        $this->assertEquals(3600, $penjualan->komisi_aplikator);
    }
}