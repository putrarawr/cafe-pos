<?php

namespace Tests\Feature;

use App\Filament\Pages\KontrolStokGudang;
use App\Models\Barang;
use App\Models\BarangGudang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KontrolStokGudangTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Gudang $gudangBar;
    private Gudang $gudangGudang;
    private JenisBarang $kategoriKopi;
    private JenisBarang $kategoriSirup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->gudangBar = Gudang::factory()->create(['nama_gudang' => 'Bar Counter']);
        $this->gudangGudang = Gudang::factory()->create(['nama_gudang' => 'Gudang Utama']);

        $this->kategoriKopi = JenisBarang::factory()->create(['nama_jenis' => 'Biji Kopi']);
        $this->kategoriSirup = JenisBarang::factory()->create(['nama_jenis' => 'Sirup']);
    }

    public function test_halaman_kontrol_stok_gudang_dapat_diakses(): void
    {
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->assertSuccessful()
            ->assertSee('Kontrol Stok Gudang')
            ->assertSee('Filter Gudang')
            ->assertSee('Filter Barang');
    }

    public function test_format_stok_berantai_selalu_menampilkan_penjelasan_satuan_terkecil(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Kopi Sachet',
            'satuan' => 'Pcs',
            'satuan_2' => 'Dus',
            'isi_satuan_2' => 24,
        ]);

        // Jika kuantitas hanya satuan terkecil (< 1 dus)
        $this->assertSame('10 Pcs', $barang->formatStokBerantai(10));

        // Jika menggunakan satuan lebih besar, wajib menyertakan rincian dalam satuan terkecil
        $this->assertSame('1 Dus (24 Pcs)', $barang->formatStokBerantai(24));
        $this->assertSame('1 Dus, 6 Pcs (30 Pcs)', $barang->formatStokBerantai(30));
    }

    public function test_kontrol_stok_gudang_menampilkan_data_dan_status_dengan_benar(): void
    {
        $barang1 = Barang::factory()->create([
            'nama_barang' => 'Kopi Arabika Gayo',
            'jenis_barang_id' => $this->kategoriKopi->id,
            'stok_minimum' => 15,
            'satuan' => 'Pack',
        ]);

        // Gudang Bar: stok 2, min 10 -> Menipis (defisit 8)
        $bgBar = BarangGudang::where('barang_id', $barang1->id)
            ->where('gudang_id', $this->gudangBar->id)
            ->first();
        if ($bgBar) {
            $bgBar->update(['stok' => 2, 'stok_minimum' => 10]);
        } else {
            $bgBar = BarangGudang::create([
                'barang_id' => $barang1->id,
                'gudang_id' => $this->gudangBar->id,
                'stok' => 2,
                'stok_minimum' => 10,
            ]);
        }

        // Gudang Utama: stok 20, min 10 -> Aman (tidak boleh tampil di tabel agar tidak memenuhi)
        $bgUtama = BarangGudang::where('barang_id', $barang1->id)
            ->where('gudang_id', $this->gudangGudang->id)
            ->first();
        if ($bgUtama) {
            $bgUtama->update(['stok' => 20, 'stok_minimum' => 10]);
        } else {
            $bgUtama = BarangGudang::create([
                'barang_id' => $barang1->id,
                'gudang_id' => $this->gudangGudang->id,
                'stok' => 20,
                'stok_minimum' => 10,
            ]);
        }

        $this->assertSame(8, $bgBar->fresh()->defisit);
        $this->assertSame('menipis', $bgBar->fresh()->status_stok);

        $this->assertSame(0, $bgUtama->fresh()->defisit);
        $this->assertSame('aman', $bgUtama->fresh()->status_stok);

        // Hanya bgBar (menipis) yang tampil di tabel, bgUtama (aman) tidak dimuat
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgBar])
            ->assertCanNotSeeTableRecords([$bgUtama]);
    }

    public function test_filter_gudang_interaktif(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Sirup Vanilla',
            'jenis_barang_id' => $this->kategoriSirup->id,
            'satuan' => 'Btl',
        ]);

        $bgBar = BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 1, 'stok_minimum' => 5]
        );
        $bgGudang = BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangGudang->id],
            ['stok' => 2, 'stok_minimum' => 5]
        );

        // Filter hanya gudang Bar: hanya tampilkan baris yang gudangnya sama persis seperti di filter
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.gudang_id', $this->gudangBar->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgBar])
            ->assertCanNotSeeTableRecords([$bgGudang]);
    }

    public function test_filter_status_stok_interaktif(): void
    {
        $barangMenipis = Barang::factory()->create([
            'nama_barang' => 'Susu Segar',
            'satuan' => 'Ltr',
        ]);
        $bgMenipis = BarangGudang::updateOrCreate(
            ['barang_id' => $barangMenipis->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 2, 'stok_minimum' => 5]
        );

        $barangHabis = Barang::factory()->create([
            'nama_barang' => 'Cokelat Bubuk',
            'satuan' => 'Kg',
        ]);
        $bgHabis = BarangGudang::updateOrCreate(
            ['barang_id' => $barangHabis->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 0, 'stok_minimum' => 5]
        );

        // Filter khusus 'habis'
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.status_stok', 'habis')
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgHabis])
            ->assertCanNotSeeTableRecords([$bgMenipis]);

        // Filter khusus 'menipis'
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.status_stok', 'menipis')
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgMenipis])
            ->assertCanNotSeeTableRecords([$bgHabis]);
    }

    public function test_filter_kategori_dan_barang_interaktif(): void
    {
        $barangKopi = Barang::factory()->create([
            'nama_barang' => 'Espresso Blend',
            'jenis_barang_id' => $this->kategoriKopi->id,
        ]);
        $bgKopi = BarangGudang::updateOrCreate(
            ['barang_id' => $barangKopi->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 3, 'stok_minimum' => 10]
        );

        $barangSirup = Barang::factory()->create([
            'nama_barang' => 'Caramel Syrup',
            'jenis_barang_id' => $this->kategoriSirup->id,
        ]);
        $bgSirup = BarangGudang::updateOrCreate(
            ['barang_id' => $barangSirup->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 4, 'stok_minimum' => 10]
        );

        // Filter kategori Sirup
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.jenis_barang_id', $this->kategoriSirup->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgSirup])
            ->assertCanNotSeeTableRecords([$bgKopi]);

        // Filter barang spesifik
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.barang_id', $barangKopi->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bgKopi])
            ->assertCanNotSeeTableRecords([$bgSirup]);
    }

    public function test_modal_detail_rincian_kontrol_stok_dapat_dimuat(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Matcha Powder',
            'satuan' => 'Pcs',
        ]);

        $bg = BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangBar->id],
            ['stok' => 1, 'stok_minimum' => 5]
        );

        $ks = KartuStok::create([
            'barang_id' => $barang->id,
            'gudang_id' => $this->gudangBar->id,
            'jenis_transaksi' => KartuStok::JENIS_KOREKSI,
            'tanggal' => now(),
            'nomer_entry' => 'ADJ-001',
            'keterangan' => 'Penyesuaian Fisik',
            'jumlah' => 2,
            'saldo' => 1,
        ]);

        // 1. Verifikasi action 'detail' terdaftar di tabel
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->assertTableActionExists('detail');

        // 2. Verifikasi konten modal detail me-render seluruh informasi dengan benar
        $view = $this->view('filament.pages.modal-detail-kontrol-stok-gudang', [
            'record' => $bg,
            'allGudangStocks' => collect([$bg]),
            'recentKartuStok' => collect([$ks]),
            'createMutasiUrl' => '/admin/perpindahan-barangs/create',
        ]);

        $view->assertSee('Matcha Powder')
            ->assertSee('Bar Counter')
            ->assertSee('ADJ-001')
            ->assertSee('Buat Perpindahan Barang');
    }
}
