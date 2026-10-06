<?php

namespace Tests\Feature;

use App\Filament\Resources\Barangs\Pages\ListBarangs;
use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PunyaMeja;
use Tests\TestCase;

class BarangCafeFieldsTest extends TestCase
{
    use RefreshDatabase, PunyaMeja;

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

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $this->denganMeja([
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
        ]));

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

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $this->denganMeja([
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
        ]));

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

    public function test_kasir_endpoint_returns_barang_kemasan_and_excludes_barang_pembantu_from_main_list()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Perlengkapan', 'kode_jenis' => 'PLK', 'deskripsi' => 'Perlengkapan']);

        $bMain = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Iced Latte',
            'harga_beli' => 8000,
            'harga_jual' => 20000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'bisa_dijual' => true,
            'status' => 'tersedia',
        ]);

        $bKemasan = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Paper Bag Coklat',
            'harga_beli' => 1000,
            'harga_jual' => 2000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'bisa_dijual' => true,
            'status' => 'tersedia',
        ]);

        $response = $this->actingAs($user)->getJson(route('kasir.data'));
        $response->assertOk();

        $mainIds = collect($response->json('barang'))->pluck('id')->all();
        $kemasanIds = collect($response->json('barangKemasan'))->pluck('id')->all();

        $this->assertContains($bMain->id, $mainIds);
        $this->assertNotContains($bKemasan->id, $mainIds);
        $this->assertContains($bKemasan->id, $kemasanIds);
    }

    public function test_kasir_store_allows_delivery_without_address()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi']);

        $b = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Espresso',
            'harga_beli' => 5000,
            'harga_jual' => 15000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'bisa_dijual' => true,
            'status' => 'tersedia',
        ]);
        $gudang->barangs()->attach($b->id, ['stok' => 20]);

        // Pesanan delivery tanpa alamat (misal via kurir ojol)
        $gofood = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $this->denganMeja([
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 15000,
            'alamat_pengiriman' => null,
            'biaya_kirim' => 0,
            'aplikator_id' => $gofood->id,
            'details' => [
                [
                    'barang_id' => $b->id,
                    'satuan' => 'Cup',
                    'jumlah' => 1,
                    'harga' => 15000,
                    'diskon' => 0,
                    'jenis_pesanan' => 'delivery',
                ],
            ],
        ]));

        $response->assertOk();

        $this->assertDatabaseHas('detail_jual', [
            'barang_id' => $b->id,
            'jenis_pesanan' => 'delivery',
        ]);
    }

    public function test_kasir_store_allows_packaging_barang_pembantu_and_reduces_stock()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Bar', 'alamat' => 'Bar']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Kemasan', 'kode_jenis' => 'KMS', 'deskripsi' => 'Kemasan']);

        $bKemasan = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Paper Bag Large',
            'harga_beli' => 1500,
            'harga_jual' => 3000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_pembantu',
            'bisa_dijual' => false, // meskipun false, tetap diizinkan karena tipe barang_pembantu
            'status' => 'tersedia',
        ]);
        $gudang->barangs()->attach($bKemasan->id, ['stok' => 50]);

        $response = $this->actingAs($user)->postJson(route('kasir.simpan'), $this->denganMeja([
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 3000,
            'details' => [
                [
                    'barang_id' => $bKemasan->id,
                    'satuan' => 'Pcs',
                    'jumlah' => 1,
                    'harga' => 3000,
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ],
        ]));

        $response->assertOk();

        // Verifikasi stok gudang berkurang dari 50 menjadi 49
        $stokGudang = $gudang->barangs()->where('barang_id', $bKemasan->id)->first()->pivot->stok;
        $this->assertEquals(49, $stokGudang);
    }
}
