<?php

namespace Tests\Feature;

use App\Filament\Resources\Barangs\Pages\ListBarangs;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarangCafeFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_barang_has_cafe_fields_with_correct_defaults()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Caramel Macchiato',
            'harga_beli' => 12000,
            'harga_jual' => 28000,
            'satuan' => 'Cup',
        ]);

        $this->assertEquals('barang_dagang', $barang->tipe_barang);
        $this->assertEquals('tersedia', $barang->status);
        $this->assertTrue($barang->bisa_dijual);
        $this->assertFalse($barang->butuh_proses);
        $this->assertNull($barang->gambar);
        $this->assertNull($barang->gambar_url);
    }

    public function test_kasir_endpoint_only_returns_bisa_dijual_true_items()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi']);

        // Item 1: Menu Jadi (bisa dijual)
        $b1 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Caffe Latte',
            'harga_beli' => 10000,
            'harga_jual' => 24000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'bisa_dijual' => true,
            'butuh_proses' => true,
            'status' => 'tersedia',
        ]);

        // Item 2: Bahan Baku (tidak bisa dijual langsung di kasir)
        $b2 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Biji Kopi Arabica 1kg',
            'harga_beli' => 120000,
            'harga_jual' => 150000,
            'satuan' => 'Kg',
            'tipe_barang' => 'bahan_baku',
            'bisa_dijual' => false,
            'butuh_proses' => false,
            'status' => 'tersedia',
        ]);

        $response = $this->actingAs($user)->getJson(route('kasir.data'));

        $response->assertOk();
        $itemIds = collect($response->json('barang'))->pluck('id')->all();

        $this->assertContains($b1->id, $itemIds);
        $this->assertNotContains($b2->id, $itemIds);
    }

    public function test_kasir_store_rejects_habis_items()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi']);

        $b = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Avocado Coffee',
            'harga_beli' => 15000,
            'harga_jual' => 30000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'bisa_dijual' => true,
            'status' => 'habis',
        ]);
        $b->gudangs()->attach($gudang->id, ['stok' => 10]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 30000,
            'details' => [
                [
                    'barang_id' => $b->id,
                    'satuan' => 'Cup',
                    'jumlah' => 1,
                    'harga' => 30000,
                    'diskon' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "Menu 'Avocado Coffee' sedang berstatus habis.",
            ]);
    }

    public function test_kasir_store_rejects_non_sellable_items()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi']);

        $b = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Susu UHT Fresh 1L',
            'harga_beli' => 18000,
            'harga_jual' => 20000,
            'satuan' => 'Kotak',
            'tipe_barang' => 'bahan_baku',
            'bisa_dijual' => false,
            'status' => 'tersedia',
        ]);
        $b->gudangs()->attach($gudang->id, ['stok' => 20]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), [
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 20000,
            'details' => [
                [
                    'barang_id' => $b->id,
                    'satuan' => 'Kotak',
                    'jumlah' => 1,
                    'harga' => 20000,
                    'diskon' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "Barang 'Susu UHT Fresh 1L' tidak dapat dijual di kasir.",
            ]);
    }

    public function test_filament_barang_table_displays_cafe_columns_and_filters()
    {
        $admin = User::factory()->create();
        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi']);

        $b1 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Americano',
            'harga_beli' => 5000,
            'harga_jual' => 15000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
        ]);

        $b2 = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Cup Plastik 16oz',
            'harga_beli' => 500,
            'harga_jual' => 600,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'status' => 'habis',
        ]);

        Livewire::actingAs($admin)
            ->test(ListBarangs::class)
            ->assertCanSeeTableRecords([$b1, $b2])
            ->filterTable('tipe_barang', 'barang_jadi')
            ->assertCanSeeTableRecords([$b1])
            ->assertCanNotSeeTableRecords([$b2]);
    }
}
