<?php

namespace Tests\Feature;

use App\Filament\Pages\RekapanPenjualan;
use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RekapanPenjualanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_rekapan_penjualan_page()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(RekapanPenjualan::class)
            ->assertSuccessful();
    }

    public function test_rekapan_penjualan_renders_and_aggregates_properly()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $bUtama = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Kapal Api',
            'harga_beli' => 2000,
            'harga_jual' => 3000,
            'satuan' => 'Pcs',
        ]);

        $bBonus = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Gula Pasir',
            'harga_beli' => 1000,
            'harga_jual' => 1500,
            'satuan' => 'Pcs',
        ]);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0001',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 30000,
            'diskon' => 0,
            'neto' => 30000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 30000,
            'kembalian' => 0,
        ]);

        DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $bUtama->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Pcs',
            'jumlah' => 10,
            'harga' => 3000,
            'hpp' => 2000,
            'diskon' => 0,
            'subtotal' => 30000,
            'is_bonus' => false,
            'bonus_barang_id' => $bBonus->id,
            'bonus_qty' => 2,
            'bonus_satuan' => 'Pcs',
            'bonus_hpp' => 1000,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanPenjualan::class)
            ->assertSee('Kopi Kapal Api')
            ->assertSee('Gula Pasir')
            ->assertSee('Rekapan Penjualan');
    }

    public function test_rekapan_penjualan_filter_barang_works()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $b1 = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Teh Botol Sosro', 'harga_beli' => 2000, 'harga_jual' => 3000, 'satuan' => 'Botol']);
        $b2 = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Air Mineral Aqua', 'harga_beli' => 1500, 'harga_jual' => 2500, 'satuan' => 'Botol']);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0002',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 15000,
            'diskon' => 0,
            'neto' => 15000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 15000,
            'kembalian' => 0,
        ]);

        DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $b1->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Botol',
            'jumlah' => 5,
            'harga' => 3000,
            'hpp' => 2000,
            'diskon' => 0,
            'subtotal' => 15000,
            'is_bonus' => false,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanPenjualan::class)
            ->set('data.barang_id', $b1->id)
            ->assertCanSeeTableRecords([$b1])
            ->assertCanNotSeeTableRecords([$b2]);
    }

    public function test_rekapan_penjualan_record_action_modal_can_be_called()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Indomie Goreng Jumbo',
            'harga_beli' => 3000,
            'harga_jual' => 4500,
            'satuan' => 'Bungkus',
        ]);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0003',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 9000,
            'diskon' => 0,
            'neto' => 9000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 9000,
            'kembalian' => 0,
        ]);

        DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Bungkus',
            'jumlah' => 2,
            'harga' => 4500,
            'hpp' => 3000,
            'diskon' => 0,
            'subtotal' => 9000,
            'is_bonus' => false,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanPenjualan::class)
            ->callTableAction('detail', $barang);
    }
}
