<?php

namespace Tests\Feature;

use App\Filament\Resources\Barangs\BarangResource;
use App\Filament\Resources\Barangs\Pages\EditBarang;
use App\Filament\Resources\Barangs\RelationManagers\GudangsRelationManager;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StokMinimumDinamisTest extends TestCase
{
    use RefreshDatabase;

    private StokService $stokService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stokService = app(StokService::class);
    }

    public function test_default_stok_minimum_model(): void
    {
        $barangDaftar = new Barang();
        $this->assertSame(20, $barangDaftar->stok_minimum);

        $barang = Barang::factory()->create([
            'tipe_barang' => 'barang_dagang',
        ]);
        $this->assertSame(20, (int) $barang->fresh()->stok_minimum);

        $barangJadi = Barang::factory()->create([
            'tipe_barang' => 'barang_jadi',
            'stok_minimum' => 0,
        ]);
        $this->assertSame(0, (int) $barangJadi->fresh()->stok_minimum);
    }

    public function test_stok_service_mewarisi_stok_minimum_dari_master_barang_saat_auto_attach(): void
    {
        $barang = Barang::factory()->create([
            'stok_minimum' => 15,
        ]);
        $gudang = Gudang::factory()->create();

        DB::transaction(fn () => $this->stokService->tambahStok($barang->id, $gudang->id, 10));

        $pivot = DB::table('barang_gudang')
            ->where('barang_id', $barang->id)
            ->where('gudang_id', $gudang->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertSame(10, (int) $pivot->stok);
        $this->assertSame(15, (int) $pivot->stok_minimum);
    }

    public function test_dashboard_widget_stok_menipis_dihitung_secara_dinamis(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Alpha']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Beta']);

        // 1. Barang menipis karena Batas Khusus Gudang (stok 4 <= stok_minimum 10 di gudang1)
        $barangMenipisGudang = Barang::factory()->create([
            'nama_barang' => 'Barang Menipis Gudang',
            'tipe_barang' => 'barang_dagang',
            'stok_minimum' => 5, // total stok toko 24 > global 5
        ]);
        $barangMenipisGudang->gudangs()->attach($gudang1->id, ['stok' => 4, 'stok_minimum' => 10]);
        $barangMenipisGudang->gudangs()->attach($gudang2->id, ['stok' => 20, 'stok_minimum' => 0]);

        // 2. Barang menipis karena Batas Global Toko (total stok toko 8 <= global 20, meski batas tiap gudang dinonaktifkan / 0)
        $barangMenipisGlobal = Barang::factory()->create([
            'nama_barang' => 'Barang Menipis Global',
            'tipe_barang' => 'barang_dagang',
            'stok_minimum' => 20,
        ]);
        $barangMenipisGlobal->gudangs()->attach($gudang1->id, ['stok' => 4, 'stok_minimum' => 0]);
        $barangMenipisGlobal->gudangs()->attach($gudang2->id, ['stok' => 4, 'stok_minimum' => 0]);

        // 3. Barang stok aman (total 50 > global 20, dan stok gudang > batas gudang)
        $barangAman = Barang::factory()->create([
            'nama_barang' => 'Barang Aman',
            'tipe_barang' => 'barang_dagang',
            'stok_minimum' => 20,
        ]);
        $barangAman->gudangs()->attach($gudang1->id, ['stok' => 25, 'stok_minimum' => 10]);
        $barangAman->gudangs()->attach($gudang2->id, ['stok' => 25, 'stok_minimum' => 10]);

        // 4. Barang jadi (harus diabaikan)
        $barangJadi = Barang::factory()->create([
            'nama_barang' => 'Barang Jadi',
            'tipe_barang' => 'barang_jadi',
            'stok_minimum' => 0,
        ]);
        $barangJadi->gudangs()->attach($gudang1->id, ['stok' => 0, 'stok_minimum' => 0]);

        // 5. Barang dengan stok_minimum = 0 (dinonaktifkan pemantauan global dan gudang)
        $barangNonaktif = Barang::factory()->create([
            'nama_barang' => 'Barang Nonaktif',
            'tipe_barang' => 'barang_dagang',
            'stok_minimum' => 0,
        ]);
        $barangNonaktif->gudangs()->attach($gudang1->id, ['stok' => 0, 'stok_minimum' => 0]);

        $lowStockQueryCount = Barang::query()
            ->where('tipe_barang', '!=', 'barang_jadi')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('barang.stok_minimum', '>', 0)
                        ->whereRaw('(SELECT COALESCE(SUM(stok), 0) FROM barang_gudang WHERE barang_gudang.barang_id = barang.id) <= barang.stok_minimum');
                })->orWhereHas('gudangs', function ($q) {
                    $q->where('barang_gudang.stok_minimum', '>', 0)
                        ->whereColumn('barang_gudang.stok', '<=', 'barang_gudang.stok_minimum');
                });
            })
            ->count();

        $this->assertSame(2, $lowStockQueryCount);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Stok Menipis');
    }

    public function test_kasir_controller_memuat_stok_minimum_dan_mapping_gudang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang = Gudang::factory()->create();
        $barang = Barang::factory()->create([
            'nama_barang' => 'Snack Keripik',
            'tipe_barang' => 'barang_dagang',
            'stok_minimum' => 18,
            'bisa_dijual' => true,
            'status' => 'tersedia',
        ]);
        $barang->gudangs()->attach($gudang->id, ['stok' => 12, 'stok_minimum' => 14]);

        $response = $this->get('/kasir');
        $response->assertSuccessful();

        $kasirData = $response->viewData('kasirData');
        $this->assertIsArray($kasirData);
        $barangList = $kasirData['barang'];
        $this->assertIsArray($barangList);

        $target = collect($barangList)->firstWhere('id', $barang->id);
        $this->assertNotNull($target);
        $this->assertSame(18, $target['stok_minimum']);
        $this->assertArrayHasKey($gudang->id, $target['stok_minimum_gudang']);
        $this->assertSame(14, $target['stok_minimum_gudang'][$gudang->id]);

        // Uji juga via endpoint JSON /kasir/data
        $jsonResponse = $this->getJson('/kasir/data');
        $jsonResponse->assertSuccessful();
        $targetJson = collect($jsonResponse->json('barang'))->firstWhere('id', $barang->id);
        $this->assertNotNull($targetJson);
        $this->assertSame(18, $targetJson['stok_minimum']);
        $this->assertSame(14, $targetJson['stok_minimum_gudang'][$gudang->id]);
    }

    public function test_gudangs_relation_manager_otomatis_menampilkan_semua_gudang_tanpa_aksi_attach_atau_detach(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Alpha']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Beta']);
        $gudang3 = Gudang::factory()->create(['nama_gudang' => 'Gudang Gamma']);

        // Barang hanya ditautkan ke Gudang Alpha awalnya
        $barang = Barang::factory()->create([
            'nama_barang' => 'Barang Auto Gudang Test',
            'stok_minimum' => 20,
        ]);
        $barang->gudangs()->attach($gudang1->id, ['stok' => 50, 'stok_minimum' => 20]);

        $component = Livewire::test(GudangsRelationManager::class, [
            'ownerRecord' => $barang,
            'pageClass' => EditBarang::class,
        ]);

        $component->assertSuccessful();

        // 1. Seluruh gudang otomatis tampil di tabel
        $component->assertSee('Gudang Alpha');
        $component->assertSee('Gudang Beta');
        $component->assertSee('Gudang Gamma');

        // 2. Tidak ada tombol Lampirkan (Attach) maupun tombol Lepaskan (Detach)
        $component->assertActionDoesNotExist('attach');
        $component->assertActionDoesNotExist('detach');

        // 3. Verifikasi data pivot di database otomatis terbentuk untuk Gudang Beta dan Gamma dengan stok 0
        $this->assertTrue($barang->gudangs()->where('gudang.id', $gudang2->id)->exists());
        $this->assertTrue($barang->gudangs()->where('gudang.id', $gudang3->id)->exists());
        $this->assertSame(0, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang2->id)->value('stok'));
        $this->assertSame(0, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang3->id)->value('stok'));
    }

    public function test_edit_barang_menyimpan_stok_minimum_global_dan_per_gudang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Depan']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Belakang']);

        $barang = Barang::factory()->create([
            'nama_barang' => 'Barang Setting Stok Min',
            'tipe_barang' => 'barang_dagang',
            'satuan' => 'Pcs',
            'stok_minimum' => 10,
            'harga_beli' => 10000,
            'harga_jual' => 20000,
        ]);

        $barang->gudangs()->attach($gudang1->id, ['stok' => 5, 'stok_minimum' => 10]);
        $barang->gudangs()->attach($gudang2->id, ['stok' => 5, 'stok_minimum' => 10]);

        Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()])
            ->fillForm([
                'stok_minimum_display' => 30,
                'satuan_stok_minimum' => 'Pcs',
                'stok_minimum_gudang_display' => [
                    $gudang1->id => 25,
                    $gudang2->id => 15,
                ],
                'satuan_stok_minimum_gudang' => [
                    $gudang1->id => 'Pcs',
                    $gudang2->id => 'Pcs',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(30, (int) $barang->fresh()->stok_minimum);
        $this->assertSame(25, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang1->id)->value('stok_minimum'));
        $this->assertSame(15, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang2->id)->value('stok_minimum'));
    }

    public function test_edit_barang_dapat_memilih_satuan_dan_otomatis_mengonversi_ke_satuan_dasar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Utama']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Cabang']);

        // Barang dengan Level 1 = Pcs, Level 2 = Dus (isi 24 Pcs)
        $barang = Barang::factory()->create([
            'nama_barang' => 'Minuman Kaleng',
            'tipe_barang' => 'barang_dagang',
            'satuan' => 'Pcs',
            'satuan_2' => 'Dus',
            'isi_satuan_2' => 24,
            'stok_minimum' => 48, // 48 Pcs = 2 Dus
            'harga_beli' => 5000,
            'harga_jual' => 8000,
        ]);

        $barang->gudangs()->attach($gudang1->id, ['stok' => 100, 'stok_minimum' => 72]); // 72 Pcs = 3 Dus
        $barang->gudangs()->attach($gudang2->id, ['stok' => 50, 'stok_minimum' => 24]);  // 24 Pcs = 1 Dus

        // 1. Verifikasi dekonstruksi otomatis saat form dimuat
        $component = Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()]);
        $component->assertSchemaStateSet([
            'stok_minimum_display' => 2,
            'satuan_stok_minimum' => 'Dus',
        ]);
        $this->assertSame(3, (int) ($component->get('data.stok_minimum_gudang_display')[$gudang1->id] ?? null));
        $this->assertSame('Dus', $component->get('data.satuan_stok_minimum_gudang')[$gudang1->id] ?? null);

        // 2. Simpan dengan satuan berbeda: Global = 4 Dus (96 Pcs), Gudang 1 = 10 Pcs (10 Pcs)
        $component->fillForm([
            'stok_minimum_display' => 4,
            'satuan_stok_minimum' => 'Dus',
            'stok_minimum_gudang_display' => [
                $gudang1->id => 10,
                $gudang2->id => 2,
            ],
            'satuan_stok_minimum_gudang' => [
                $gudang1->id => 'Pcs',
                $gudang2->id => 'Dus',
            ],
        ])
            ->call('save')
            ->assertHasNoFormErrors();

        // 3. Verifikasi tersimpan dalam Satuan Dasar (Level 1) di database
        $this->assertSame(96, (int) $barang->fresh()->stok_minimum); // 4 Dus * 24 = 96 Pcs
        $this->assertSame(10, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang1->id)->value('stok_minimum')); // 10 Pcs * 1 = 10 Pcs
        $this->assertSame(48, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang2->id)->value('stok_minimum')); // 2 Dus * 24 = 48 Pcs
    }

    public function test_toggle_off_mematikan_pemantauan_stok_minimum_global_dan_gudang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Alpha']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Beta']);

        $barang = Barang::factory()->create([
            'nama_barang' => 'Barang Toggle Test',
            'tipe_barang' => 'barang_dagang',
            'satuan' => 'Pcs',
            'stok_minimum' => 20,
            'harga_beli' => 5000,
            'harga_jual' => 10000,
        ]);

        $barang->gudangs()->attach($gudang1->id, ['stok' => 10, 'stok_minimum' => 15]);
        $barang->gudangs()->attach($gudang2->id, ['stok' => 10, 'stok_minimum' => 25]);

        // Matikan pemantauan global dan Gudang Alpha, biarkan Gudang Beta aktif
        Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()])
            ->fillForm([
                'pantau_stok_global' => false,
                'status_pantau_gudang' => [
                    $gudang1->id => false,
                    $gudang2->id => true,
                ],
                'stok_minimum_gudang_display' => [
                    $gudang2->id => 30,
                ],
                'satuan_stok_minimum_gudang' => [
                    $gudang2->id => 'Pcs',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Verifikasi global dan Gudang Alpha menjadi 0 (dimatikan), Gudang Beta tetap aktif
        $this->assertSame(0, (int) $barang->fresh()->stok_minimum);
        $this->assertSame(0, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang1->id)->value('stok_minimum'));
        $this->assertSame(30, (int) DB::table('barang_gudang')->where('barang_id', $barang->id)->where('gudang_id', $gudang2->id)->value('stok_minimum'));
    }

    public function test_form_otomatis_mendeteksi_status_toggle_dari_nilai_stok_minimum_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gudang1 = Gudang::factory()->create(['nama_gudang' => 'Gudang Mati']);
        $gudang2 = Gudang::factory()->create(['nama_gudang' => 'Gudang Hidup']);

        // Global mati (0), Gudang 1 mati (0), Gudang 2 aktif (15)
        $barang = Barang::factory()->create([
            'nama_barang' => 'Barang Cek Hidrasi',
            'tipe_barang' => 'barang_dagang',
            'satuan' => 'Pcs',
            'stok_minimum' => 0,
            'harga_beli' => 5000,
            'harga_jual' => 10000,
        ]);

        $barang->gudangs()->attach($gudang1->id, ['stok' => 0, 'stok_minimum' => 0]);
        $barang->gudangs()->attach($gudang2->id, ['stok' => 50, 'stok_minimum' => 15]);

        $component = Livewire::test(EditBarang::class, ['record' => $barang->getRouteKey()]);

        // Verifikasi status toggle otomatis terhidrasi sesuai nilai database
        $this->assertFalse((bool) $component->get('data.pantau_stok_global'));
        $this->assertFalse((bool) ($component->get('data.status_pantau_gudang')[$gudang1->id] ?? true));
        $this->assertTrue((bool) ($component->get('data.status_pantau_gudang')[$gudang2->id] ?? false));
    }
}

