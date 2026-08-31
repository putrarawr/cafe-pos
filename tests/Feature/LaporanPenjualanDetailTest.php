<?php

namespace Tests\Feature;

use App\Filament\Resources\LaporanPenjualanDetails\LaporanPenjualanDetailResource;
use App\Filament\Resources\LaporanPenjualanDetails\Pages\ListLaporanPenjualanDetails;
use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanPenjualanDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_jual_dapat_menyimpan_kolom_bonus_dan_menghitung_laba_bersih()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Elektronik', 'kode_jenis' => 'ELK', 'deskripsi' => 'Kategori Elektronik']);
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama']);
        $user = User::factory()->create();

        $barangUtama = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Smartphone Pro 12',
            'harga_beli' => 7000000,
            'hpp' => 7000000,
            'harga_jual' => 10000000,
            'satuan' => 'Unit',
        ]);

        $barangBonus = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Case Smartphone',
            'harga_beli' => 100000,
            'hpp' => 100000,
            'harga_jual' => 200000,
            'satuan' => 'Pcs',
        ]);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0001',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => '2026-08-31',
            'total' => 20000000,
            'diskon' => 0,
            'neto' => 20000000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000000,
            'kembalian' => 0,
        ]);

        $detail = DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $barangUtama->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Unit',
            'jumlah' => 2,
            'harga' => 10000000,
            'hpp' => 7000000,
            'diskon' => 0,
            'subtotal' => 20000000,
            'is_bonus' => false,
            'bonus_barang_id' => $barangBonus->id,
            'bonus_qty' => 2,
            'bonus_satuan' => 'Pcs',
            'bonus_hpp' => 100000,
        ]);

        // Verifikasi database has
        $this->assertDatabaseHas('detail_jual', [
            'id' => $detail->id,
            'bonus_barang_id' => $barangBonus->id,
            'bonus_qty' => 2,
            'bonus_satuan' => 'Pcs',
            'bonus_hpp' => 100000,
        ]);

        // Verifikasi relasi
        $this->assertEquals('Case Smartphone', $detail->bonusBarang->nama_barang);

        // Verifikasi kalkulasi laba bersih:
        // Subtotal (20.000.000) - [ (2 * 7.000.000) + (2 * 100.000) ] = 20.000.000 - 14.200.000 = 5.800.000
        $this->assertEquals(5800000, $detail->laba_bersih);
    }

    public function test_resource_laporan_penjualan_detail_bersifat_read_only()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Kategori Makanan']);
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat']);
        $user = User::factory()->create();

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Indomie Rasa Soto',
            'harga_beli' => 2500,
            'hpp' => 2500,
            'harga_jual' => 3500,
            'satuan' => 'Bungkus',
        ]);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0002',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => '2026-08-31',
            'total' => 3500,
            'diskon' => 0,
            'neto' => 3500,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 3500,
            'kembalian' => 0,
        ]);

        $detail = DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Bungkus',
            'jumlah' => 1,
            'harga' => 3500,
            'hpp' => 2500,
            'diskon' => 0,
            'subtotal' => 3500,
            'is_bonus' => false,
        ]);

        $this->assertFalse(LaporanPenjualanDetailResource::canCreate());
        $this->assertFalse(LaporanPenjualanDetailResource::canEdit($detail));
        $this->assertFalse(LaporanPenjualanDetailResource::canDelete($detail));
        $this->assertFalse(LaporanPenjualanDetailResource::canDeleteAny());
    }

    public function test_halaman_laporan_penjualan_detail_dapat_dirender_tanpa_error()
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(ListLaporanPenjualanDetails::class)
            ->assertSuccessful();
    }
}
