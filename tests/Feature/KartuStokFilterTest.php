<?php

namespace Tests\Feature;

use App\Filament\Resources\KartuStoks\Pages\ListKartuStoks;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KartuStokFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_stok_tampil_kosong_sebelum_di_filter()
    {
        $user = User::factory()->create();
        $jenis = JenisBarang::create(['nama_jenis' => 'Umum', 'kode_jenis' => 'UMM', 'deskripsi' => 'Deskripsi']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Barang A',
            'harga_beli' => 1000,
            'harga_jual' => 1200,
            'satuan' => 'Pcs',
        ]);
        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Utama',
            'lokasi' => 'Pusat',
        ]);

        KartuStok::create([
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'nomer_entry' => 'ENTRY-001',
            'tanggal' => now(),
            'jenis_transaksi' => 'masuk',
            'jumlah' => 10,
            'harga' => 1000,
            'saldo' => 10,
            'keterangan' => 'Stok Awal',
        ]);

        Livewire::actingAs($user)
            ->test(ListKartuStoks::class)
            ->assertCanSeeTableRecords([])
            ->assertCountTableRecords(0);
    }

    public function test_kartu_stok_menampilkan_data_setelah_di_filter()
    {
        $user = User::factory()->create();
        $jenis = JenisBarang::create(['nama_jenis' => 'Umum', 'kode_jenis' => 'UMM', 'deskripsi' => 'Deskripsi']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Barang B',
            'harga_beli' => 1000,
            'harga_jual' => 1200,
            'satuan' => 'Pcs',
        ]);
        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Utama',
            'lokasi' => 'Pusat',
        ]);

        $stok = KartuStok::create([
            'barang_id' => $barang->id,
            'gudang_id' => $gudang->id,
            'nomer_entry' => 'ENTRY-002',
            'tanggal' => now(),
            'jenis_transaksi' => 'masuk',
            'jumlah' => 5,
            'harga' => 1000,
            'saldo' => 5,
            'keterangan' => 'Stok Masuk',
        ]);

        Livewire::actingAs($user)
            ->test(ListKartuStoks::class)
            ->filterTable('barang_id', $barang->id)
            ->assertCanSeeTableRecords([$stok])
            ->assertCountTableRecords(1);
    }
}
