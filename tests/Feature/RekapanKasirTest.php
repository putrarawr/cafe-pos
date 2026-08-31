<?php

namespace Tests\Feature;

use App\Filament\Pages\RekapanKasir;
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

class RekapanKasirTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_rekapan_kasir_page()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->assertSuccessful();
    }

    public function test_rekapan_kasir_aggregates_payment_methods_correctly()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $karyawan = Karyawan::create([
            'nama_karyawan' => 'Siti Kasir',
            'posisi' => 'Kasir',
            'no_telp' => '08123456789',
            'password' => bcrypt('password'),
        ]);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Kapal Api',
            'harga_beli' => 2000,
            'harga_jual' => 3000,
            'satuan' => 'Pcs',
        ]);

        // Transaksi 1: Tunai
        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0001',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 15000,
            'diskon' => 0,
            'neto' => 15000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000,
            'kembalian' => 5000,
        ]);

        DetailJual::create([
            'penjualan_id' => $p1->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Pcs',
            'jumlah' => 5,
            'harga' => 3000,
            'hpp' => 2000,
            'diskon' => 0,
            'subtotal' => 15000,
            'is_bonus' => false,
        ]);

        // Transaksi 2: QRIS
        $p2 = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0002',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 30000,
            'diskon' => 0,
            'neto' => 30000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 30000,
            'kembalian' => 0,
        ]);

        DetailJual::create([
            'penjualan_id' => $p2->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Pcs',
            'jumlah' => 10,
            'harga' => 3000,
            'hpp' => 2000,
            'diskon' => 0,
            'subtotal' => 30000,
            'is_bonus' => false,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->assertSee('Siti Kasir')
            ->assertSee('Rekapan Kasir')
            ->assertSee('Tunai');
    }

    public function test_rekapan_kasir_filter_kasir_works()
    {
        $user = User::factory()->create(['name' => 'Manager Toko']);
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);

        $k1 = Karyawan::create(['nama_karyawan' => 'Budi Kasir Satu', 'posisi' => 'Kasir', 'no_telp' => '08111111', 'password' => bcrypt('password')]);
        $k2 = Karyawan::create(['nama_karyawan' => 'Ani Kasir Dua', 'posisi' => 'Kasir', 'no_telp' => '08222222', 'password' => bcrypt('password')]);

        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Roti Manis', 'harga_beli' => 2000, 'harga_jual' => 3000, 'satuan' => 'Pcs']);

        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0003',
            'karyawan_id' => $k1->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 6000,
            'diskon' => 0,
            'neto' => 6000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 10000,
            'kembalian' => 4000,
        ]);
        DetailJual::create(['penjualan_id' => $p1->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Pcs', 'jumlah' => 2, 'harga' => 3000, 'hpp' => 2000, 'diskon' => 0, 'subtotal' => 6000, 'is_bonus' => false]);

        $p2 = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0004',
            'karyawan_id' => $k2->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 9000,
            'diskon' => 0,
            'neto' => 9000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 9000,
            'kembalian' => 0,
        ]);
        DetailJual::create(['penjualan_id' => $p2->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Pcs', 'jumlah' => 3, 'harga' => 3000, 'hpp' => 2000, 'diskon' => 0, 'subtotal' => 9000, 'is_bonus' => false]);

        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->set('data.kasir', 'karyawan_' . $k1->getKey())
            ->assertCanSeeTableRecords([$p1])
            ->assertCanNotSeeTableRecords([$p2]);
    }

    public function test_rekapan_kasir_record_action_modal_can_be_called()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);

        $karyawan = Karyawan::create(['nama_karyawan' => 'Joko Kasir', 'posisi' => 'Kasir', 'no_telp' => '08333333', 'password' => bcrypt('password')]);
        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Snack Ring', 'harga_beli' => 1000, 'harga_jual' => 2000, 'satuan' => 'Bungkus']);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260831-0005',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 4000,
            'diskon' => 0,
            'neto' => 4000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 5000,
            'kembalian' => 1000,
        ]);

        DetailJual::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Bungkus',
            'jumlah' => 2,
            'harga' => 2000,
            'hpp' => 1000,
            'diskon' => 0,
            'subtotal' => 4000,
            'is_bonus' => false,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->callTableAction('detail', $penjualan);
    }
}
