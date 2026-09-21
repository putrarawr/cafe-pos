<?php

namespace Tests\Feature;

use App\Filament\Resources\Aplikators\Pages\CreateAplikator;
use App\Filament\Resources\Aplikators\Pages\EditAplikator;
use App\Filament\Resources\Aplikators\Pages\ListAplikators;
use App\Filament\Resources\Barangs\Pages\CreateBarang;
use App\Filament\Resources\Barangs\Pages\EditBarang;
use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\BarangHargaAplikator;
use App\Models\JenisBarang;
use App\Models\User;
use Database\Seeders\AplikatorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HargaDeliveryAplikatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplikator_seeder_populates_default_data()
    {
        $this->seed(AplikatorSeeder::class);

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'GOFOOD',
            'nama_aplikator' => 'GoFood',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'GRABFOOD',
            'nama_aplikator' => 'GrabFood',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'SHOPEEFOOD',
            'nama_aplikator' => 'ShopeeFood',
        ]);

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'MAXIM',
            'nama_aplikator' => 'Maxim Food',
        ]);

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'TOKO',
            'nama_aplikator' => 'Kurir Toko / Internal',
            'persentase_komisi' => 0.00,
        ]);
    }

    public function test_aplikator_model_crud_and_scope_aktif()
    {
        $aktif = Aplikator::create([
            'nama_aplikator' => 'Aplikator A',
            'kode_aplikator' => 'APP_A',
            'persentase_komisi' => 10.00,
            'status_aktif' => true,
        ]);

        $nonAktif = Aplikator::create([
            'nama_aplikator' => 'Aplikator B',
            'kode_aplikator' => 'APP_B',
            'persentase_komisi' => 15.00,
            'status_aktif' => false,
        ]);

        $listAktif = Aplikator::aktif()->pluck('id')->all();

        $this->assertContains($aktif->id, $listAktif);
        $this->assertNotContains($nonAktif->id, $listAktif);
    }

    public function test_barang_harga_aplikator_relation_and_pricing()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Latte',
            'harga_beli' => 10000,
            'harga_jual' => 20000, // Harga Dine In & Take Away
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_olahan',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

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

        // Tambah harga per aplikator
        $barang->hargaAplikators()->create([
            'aplikator_id' => $gofood->id,
            'harga_jual' => 25000,
        ]);

        $barang->hargaAplikators()->create([
            'aplikator_id' => $grabfood->id,
            'harga_jual' => 24000,
        ]);

        $barang->refresh();

        $this->assertCount(2, $barang->hargaAplikators);
        $this->assertCount(2, $barang->aplikators);

        $this->assertEquals(20000, $barang->harga_jual); // Base price unchanged
        $this->assertEquals(25000, $barang->hargaAplikators->where('aplikator_id', $gofood->id)->first()->harga_jual);
        $this->assertEquals(24000, $barang->hargaAplikators->where('aplikator_id', $grabfood->id)->first()->harga_jual);
    }

    public function test_barang_harga_aplikator_unique_constraint()
    {
        $this->expectException(QueryException::class);

        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Americano',
            'harga_beli' => 8000,
            'harga_jual' => 15000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_olahan',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

        $gofood = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        // First insert succeeds
        BarangHargaAplikator::create([
            'barang_id' => $barang->id,
            'aplikator_id' => $gofood->id,
            'harga_jual' => 18000,
        ]);

        // Second insert with same barang_id and aplikator_id must fail unique constraint
        BarangHargaAplikator::create([
            'barang_id' => $barang->id,
            'aplikator_id' => $gofood->id,
            'harga_jual' => 19000,
        ]);
    }

    public function test_cascade_delete_removes_harga_aplikator()
    {
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Croissant',
            'harga_beli' => 12000,
            'harga_jual' => 22000,
            'satuan' => 'Pcs',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

        $aplikator = Aplikator::create([
            'nama_aplikator' => 'ShopeeFood',
            'kode_aplikator' => 'SHOPEEFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        $harga = BarangHargaAplikator::create([
            'barang_id' => $barang->id,
            'aplikator_id' => $aplikator->id,
            'harga_jual' => 26000,
        ]);

        $this->assertDatabaseHas('barang_harga_aplikator', ['id' => $harga->id]);

        $barang->delete();

        $this->assertDatabaseMissing('barang_harga_aplikator', ['id' => $harga->id]);
    }

    public function test_filament_aplikator_resource_pages()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListAplikators::class)
            ->assertSuccessful();

        Livewire::test(CreateAplikator::class)
            ->fillForm([
                'nama_aplikator' => 'AirAsia Food',
                'kode_aplikator' => 'AIRASIA',
                'persentase_komisi' => 18.00,
                'status_aktif' => true,
                'keterangan' => 'Aplikator testing',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('aplikator', [
            'kode_aplikator' => 'AIRASIA',
            'nama_aplikator' => 'AirAsia Food',
            'persentase_komisi' => 18.00,
        ]);

        $aplikator = Aplikator::where('kode_aplikator', 'AIRASIA')->first();

        Livewire::test(EditAplikator::class, ['record' => $aplikator->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'persentase_komisi' => 22.50,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('aplikator', [
            'id' => $aplikator->id,
            'persentase_komisi' => 22.50,
        ]);
    }

    public function test_filament_barang_edit_repeater_saves_harga_aplikator()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi Deskripsi']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Espresso Single',
            'harga_beli' => 5000,
            'harga_jual' => 12000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

        $aplikator = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'hargaAplikators' => [
                    [
                        'aplikator_id' => $aplikator->id,
                        'harga_jual' => 15000,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('barang_harga_aplikator', [
            'barang_id' => $barang->id,
            'aplikator_id' => $aplikator->id,
            'harga_jual' => 15000,
        ]);
    }

    public function test_repeater_auto_calculates_delivery_price_when_aplikator_selected_in_edit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $jenis = JenisBarang::create(['nama_jenis' => 'Kopi', 'kode_jenis' => 'KPI', 'deskripsi' => 'Kopi Deskripsi']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Caramel Macchiato',
            'harga_beli' => 8000,
            'harga_jual' => 20000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

        $aplikator = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20.00,
            'status_aktif' => true,
        ]);

        // When setting aplikator_id in the repeater, harga_jual should automatically become 24000 (20000 + 20%)
        Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()])
            ->assertSuccessful()
            ->set('data.hargaAplikators.record-0.aplikator_id', $aplikator->id)
            ->assertSet('data.hargaAplikators.record-0.harga_jual', 24000)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('barang_harga_aplikator', [
            'barang_id' => $barang->id,
            'aplikator_id' => $aplikator->id,
            'harga_jual' => 24000,
        ]);
    }

    public function test_repeater_auto_calculates_delivery_price_in_create_barang()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan Ringan']);
        $aplikator = Aplikator::create([
            'nama_aplikator' => 'Maxim Food',
            'kode_aplikator' => 'MAXIM',
            'persentase_komisi' => 15.00,
            'status_aktif' => true,
        ]);

        Livewire::test(CreateBarang::class)
            ->assertSuccessful()
            ->set('data.harga_jual', 10000)
            ->set('data.hargaAplikators.record-0.aplikator_id', $aplikator->id)
            ->assertSet('data.hargaAplikators.record-0.harga_jual', 11500);
    }

    public function test_aplikator_has_gambar_and_kasir_endpoint_returns_gambar()
    {
        $this->seed(AplikatorSeeder::class);

        $gofood = Aplikator::where('kode_aplikator', 'GOFOOD')->first();
        $this->assertNotNull($gofood->gambar);
        $this->assertStringContainsString('aplikator/gofood.svg', $gofood->gambar_url);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('kasir.data'));
        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('aplikator', $data);

        $gofoodData = collect($data['aplikator'])->firstWhere('kode_aplikator', 'GOFOOD');
        $this->assertNotNull($gofoodData);
        $this->assertArrayHasKey('gambar', $gofoodData);
        $this->assertStringContainsString('aplikator/gofood.svg', $gofoodData['gambar']);

        $tokoData = collect($data['aplikator'])->firstWhere('kode_aplikator', 'TOKO');
        $this->assertNotNull($tokoData);
        $this->assertNull($tokoData['gambar']);
    }

    public function test_filament_aplikator_edit_saves_gambar()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $aplikator = Aplikator::create([
            'nama_aplikator' => 'Lalamove',
            'kode_aplikator' => 'LALAMOVE',
            'persentase_komisi' => 10.00,
            'status_aktif' => true,
        ]);

        $file = UploadedFile::fake()->create('lalamove.png', 50, 'image/png');

        Livewire::test(EditAplikator::class, ['record' => $aplikator->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'gambar' => $file,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $aplikator->refresh();
        $this->assertNotNull($aplikator->gambar);
        $this->assertStringContainsString('aplikator/', $aplikator->gambar);
    }
}




