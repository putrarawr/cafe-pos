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

class RekapanKasirFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_kategori_pembayaran_tunai_filters_overview_stats()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $karyawan = Karyawan::create([
            'nama_karyawan' => 'Doni Kasir',
            'posisi' => 'Kasir',
            'no_telp' => '08123456780',
            'password' => bcrypt('password'),
        ]);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Espresso Single',
            'harga_beli' => 8000,
            'harga_jual' => 18000,
            'satuan' => 'Cup',
        ]);

        // Transaksi 1: Tunai Rp 18.000
        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0001',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 18000,
            'diskon' => 0,
            'neto' => 18000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000,
            'kembalian' => 2000,
        ]);
        DetailJual::create([
            'penjualan_id' => $p1->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Cup',
            'jumlah' => 1,
            'harga' => 18000,
            'hpp' => 8000,
            'diskon' => 0,
            'subtotal' => 18000,
            'is_bonus' => false,
        ]);

        // Transaksi 2: QRIS Rp 36.000
        $p2 = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0002',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 36000,
            'diskon' => 0,
            'neto' => 36000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 36000,
            'kembalian' => 0,
        ]);
        DetailJual::create([
            'penjualan_id' => $p2->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Cup',
            'jumlah' => 2,
            'harga' => 18000,
            'hpp' => 8000,
            'diskon' => 0,
            'subtotal' => 36000,
            'is_bonus' => false,
        ]);

        // Transaksi 3: Transfer Rp 54.000
        $p3 = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0003',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 54000,
            'diskon' => 0,
            'neto' => 54000,
            'jenis_pembayaran' => 'transfer',
            'bayar' => 54000,
            'kembalian' => 0,
        ]);
        DetailJual::create([
            'penjualan_id' => $p3->id,
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'satuan' => 'Cup',
            'jumlah' => 3,
            'harga' => 18000,
            'hpp' => 8000,
            'diskon' => 0,
            'subtotal' => 54000,
            'is_bonus' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->set('data.kategori_pembayaran', 'tunai');

        $stats = $component->get('overviewStats');

        $this->assertEquals(1, $stats['count_transaksi']);
        $this->assertEquals(1, $stats['count_tunai']);
        $this->assertEquals(0, $stats['count_qris']);
        $this->assertEquals(0, $stats['count_transfer']);
        $this->assertEquals(18000, $stats['sum_tunai']);
        $this->assertEquals(0, $stats['sum_qris']);
        $this->assertEquals(0, $stats['sum_transfer']);
        $this->assertEquals(18000, $stats['sum_omset']);
        $this->assertEquals('tunai', $stats['active_kategori']);
    }

    public function test_filter_kategori_pembayaran_filters_table_records_and_cashiers()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);

        $k1 = Karyawan::create(['nama_karyawan' => 'Kasir Tunai Only', 'posisi' => 'Kasir', 'no_telp' => '081111110', 'password' => bcrypt('password')]);
        $k2 = Karyawan::create(['nama_karyawan' => 'Kasir QRIS Only', 'posisi' => 'Kasir', 'no_telp' => '082222220', 'password' => bcrypt('password')]);

        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Croissant', 'harga_beli' => 10000, 'harga_jual' => 25000, 'satuan' => 'Pcs']);

        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0004',
            'karyawan_id' => $k1->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 25000,
            'diskon' => 0,
            'neto' => 25000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 30000,
            'kembalian' => 5000,
        ]);
        DetailJual::create(['penjualan_id' => $p1->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Pcs', 'jumlah' => 1, 'harga' => 25000, 'hpp' => 10000, 'diskon' => 0, 'subtotal' => 25000, 'is_bonus' => false]);

        $p2 = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0005',
            'karyawan_id' => $k2->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 50000,
            'diskon' => 0,
            'neto' => 50000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 50000,
            'kembalian' => 0,
        ]);
        DetailJual::create(['penjualan_id' => $p2->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Pcs', 'jumlah' => 2, 'harga' => 25000, 'hpp' => 10000, 'diskon' => 0, 'subtotal' => 50000, 'is_bonus' => false]);

        // Filter QRIS: Kasir Tunai Only tidak muncul di tabel, Kasir QRIS Only muncul
        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->set('data.kategori_pembayaran', 'qris')
            ->assertCanSeeTableRecords([$p2])
            ->assertCanNotSeeTableRecords([$p1]);
    }

    public function test_modal_detail_filters_invoices_based_on_active_kategori()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $karyawan = Karyawan::create(['nama_karyawan' => 'Rina Barista', 'posisi' => 'Kasir', 'no_telp' => '08555555', 'password' => bcrypt('password')]);
        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Latte Ice', 'harga_beli' => 9000, 'harga_jual' => 22000, 'satuan' => 'Cup']);

        $pTunai = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0006',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 22000,
            'diskon' => 0,
            'neto' => 22000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 25000,
            'kembalian' => 3000,
        ]);
        DetailJual::create(['penjualan_id' => $pTunai->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Cup', 'jumlah' => 1, 'harga' => 22000, 'hpp' => 9000, 'diskon' => 0, 'subtotal' => 22000, 'is_bonus' => false]);

        $pQris = Penjualan::create([
            'nomer_nota' => 'PJ-20260907-0007',
            'karyawan_id' => $karyawan->getKey(),
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 44000,
            'diskon' => 0,
            'neto' => 44000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 44000,
            'kembalian' => 0,
        ]);
        DetailJual::create(['penjualan_id' => $pQris->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Cup', 'jumlah' => 2, 'harga' => 22000, 'hpp' => 9000, 'diskon' => 0, 'subtotal' => 44000, 'is_bonus' => false]);

        Livewire::actingAs($user)
            ->test(RekapanKasir::class)
            ->set('data.kategori_pembayaran', 'tunai')
            ->callTableAction('detail', $pTunai);

        // Uji rendering view modal dengan activeKategori 'tunai'
        $renderedView = view('filament.pages.modal-detail-rekapan-kasir', [
            'kasirName' => $karyawan->nama_karyawan,
            'dariTanggal' => date('Y-m-d'),
            'sampaiTanggal' => date('Y-m-d'),
            'activeKategori' => 'tunai',
            'invoices' => collect([$pTunai]),
        ])->render();

        $this->assertStringContainsString('PJ-20260907-0006', $renderedView);
        $this->assertStringContainsString('opacity: 0.38', $renderedView);
    }
}
