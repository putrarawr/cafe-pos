<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\DetailBeli;
use App\Models\Gudang;
use App\Models\HistoriHpp;
use App\Models\JenisBarang;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoriHppTest extends TestCase
{
    use RefreshDatabase;

    public function test_terapkan_pembelian_mencatat_histori_hpp()
    {
        $user = \App\Models\User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $supplier = Supplier::create(['nama_supplier' => 'PT Sumber Makmur', 'no_telepon' => '081234567890', 'alamat' => 'Jakarta', 'status' => 'aktif']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Kategori Makanan']);

        // Barang awal: harga_beli = 2000, hpp = 2000, stok = 10 pcs
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Indomie Rasa Ayam',
            'harga_beli' => 2000,
            'hpp' => 2000,
            'harga_jual' => 3000,
            'satuan' => 'Pcs',
        ]);
        $barang->gudangs()->attach($gudang->id, ['stok' => 10]);

        // Transaksi Pembelian baru: beli 10 pcs @ 3000
        $pembelian = Pembelian::create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'nomer_entry' => 'PB-20260824-001',
            'tanggal' => date('Y-m-d'),
            'total' => 30000,
        ]);

        DetailBeli::create([
            'pembelian_id' => $pembelian->id,
            'barang_id' => $barang->id,
            'jumlah' => 10,
            'satuan' => 'Pcs',
            'harga' => 3000,
            'subtotal' => 30000,
        ]);

        // Jalankan terapkanPembelian
        $stokService = app(StokService::class);
        $stokService->terapkanPembelian($pembelian);

        // Moving Average HPP: (10*2000 + 10*3000) / 20 = 50000 / 20 = 2500
        $barang->refresh();
        $this->assertEquals(3000, $barang->harga_beli);
        $this->assertEquals(2500, $barang->hpp);

        // Verifikasi record histori_hpp tercatat
        $this->assertDatabaseHas('histori_hpp', [
            'barang_id' => $barang->id,
            'pembelian_id' => $pembelian->id,
            'supplier_id' => $supplier->id,
            'nomer_entry' => 'PB-20260824-001',
            'stok_sebelum' => 10,
            'stok_sesudah' => 20,
            'qty_beli' => 10,
            'satuan' => 'Pcs',
            'harga_beli_masuk' => 3000,
            'hpp_sebelum' => 2000,
            'hpp_sesudah' => 2500,
        ]);

        $this->assertCount(1, $barang->historiHpps()->get());
        $this->assertEquals('PT Sumber Makmur', $barang->historiHpps->first()->supplier->nama_supplier);
    }

    public function test_terapkan_pembelian_satuan_bertingkat_mencatat_qty_dan_harga_satuan_riil()
    {
        $user = \App\Models\User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $supplier = Supplier::create(['nama_supplier' => 'PT Gudang Garam', 'no_telepon' => '081234567891', 'alamat' => 'Kediri', 'status' => 'aktif']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Rokok', 'kode_jenis' => 'ROK', 'deskripsi' => 'Kategori Rokok']);

        // Barang: satuan = Bungkus, satuan_2 = Slof (isi 10 bungkus), harga_beli_2 = 218000
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Garam Gudang Filter 12',
            'harga_beli' => 21800,
            'hpp' => 21800,
            'harga_jual' => 24000,
            'satuan' => 'Bungkus',
            'satuan_2' => 'Slof',
            'isi_satuan_2' => 10,
            'harga_beli_2' => 218000,
        ]);
        $barang->gudangs()->attach($gudang->id, ['stok' => 50]);

        // Beli 10 Slof @ 218.000 / Slof (= 100 bungkus)
        $pembelian = Pembelian::create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'nomer_entry' => 'PB-20260824-002',
            'tanggal' => date('Y-m-d'),
            'total' => 2180000,
        ]);

        DetailBeli::create([
            'pembelian_id' => $pembelian->id,
            'barang_id' => $barang->id,
            'jumlah' => 10,
            'satuan' => 'Slof',
            'harga' => 218000,
            'subtotal' => 2180000,
        ]);

        $stokService = app(StokService::class);
        $stokService->terapkanPembelian($pembelian);

        $this->assertDatabaseHas('histori_hpp', [
            'barang_id' => $barang->id,
            'nomer_entry' => 'PB-20260824-002',
            'stok_sebelum' => 50,
            'stok_sesudah' => 150,
            'qty_beli' => 10,
            'satuan' => 'Slof',
            'harga_beli_masuk' => 218000,
        ]);

        // Verifikasi kartu stok tercatat 100 bungkus @ 21.800
        $this->assertDatabaseHas('kartu_stok', [
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'nomer_entry' => 'PB-20260824-002',
            'jumlah' => 100,
            'harga' => 21800,
            'saldo' => 150,
        ]);
    }

    public function test_halaman_histori_hpp_dapat_dirender_tanpa_error()
    {
        $user = \App\Models\User::factory()->create();
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\HistoriHpps\Pages\ListHistoriHpps::class)
            ->assertSuccessful();
    }
}
