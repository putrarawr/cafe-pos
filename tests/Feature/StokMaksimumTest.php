<?php

namespace Tests\Feature;

use App\Filament\Pages\KontrolStokGudang;
use App\Filament\Resources\Pembelians\Schemas\PembelianForm;
use App\Filament\Resources\PerpindahanBarangs\Schemas\PerpindahanBarangForm;
use App\Models\Barang;
use App\Models\BarangGudang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StokMaksimumTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Gudang $gudangUtama;
    private Gudang $barChiller;
    private JenisBarang $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->gudangUtama = Gudang::factory()->create(['nama_gudang' => 'Gudang Utama']);
        $this->barChiller = Gudang::factory()->create(['nama_gudang' => 'Bar / Chiller']);
        $this->kategori = JenisBarang::factory()->create(['nama_jenis' => 'Bahan Baku']);
    }

    public function test_model_barang_gudang_mendeteksi_status_overstock(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Susu UHT Fresh',
            'satuan' => 'Kotak',
            'jenis_barang_id' => $this->kategori->id,
            'stok_minimum' => 5,
            'stok_maksimum' => 20,
        ]);

        $bg = BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->barChiller->id],
            ['stok' => 25, 'stok_minimum' => 5, 'stok_maksimum' => 20]
        );

        // Kasus 1: stok melebihi stok_maksimum
        $this->assertTrue($bg->is_overstock);
        $this->assertSame(5, $bg->kelebihan_stok);
        $this->assertSame('overstock', $bg->status_stok);

        // Kasus 2: stok di bawah maksimum
        $bg->update([
            'stok' => 15,
            'stok_minimum' => 5,
            'stok_maksimum' => 20,
        ]);

        $bg->refresh();
        $this->assertFalse($bg->is_overstock);
        $this->assertSame(0, $bg->kelebihan_stok);
        $this->assertSame('aman', $bg->status_stok);

        // Kasus 3: stok_maksimum = 0 (bebas / unlimited)
        $bg->update([
            'stok' => 100,
            'stok_minimum' => 5,
            'stok_maksimum' => 0,
        ]);

        $bg->refresh();
        $this->assertFalse($bg->is_overstock);
        $this->assertSame(0, $bg->kelebihan_stok);
        $this->assertSame('aman', $bg->status_stok);
    }

    public function test_pembelian_form_mendeteksi_peringatan_stok_maksimum_gudang_tujuan(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Kopi Arabika',
            'satuan' => 'Pcs',
            'satuan_2' => 'Dus',
            'isi_satuan_2' => 10,
            'jenis_barang_id' => $this->kategori->id,
        ]);

        // Atur stok saat ini 15 Pcs, maksimum 20 Pcs di Gudang Utama
        BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangUtama->id],
            ['stok' => 15, 'stok_minimum' => 5, 'stok_maksimum' => 20]
        );

        // Pembelian 3 Pcs -> total 18 <= 20 (tidak overstock)
        $checkAman = PembelianForm::cekOverstockItem($barang->id, 'Pcs', 3, $this->gudangUtama->id);
        $this->assertFalse($checkAman['is_overstock']);

        // Pembelian 10 Pcs -> total 25 > 20 (overstock)
        $checkOver = PembelianForm::cekOverstockItem($barang->id, 'Pcs', 10, $this->gudangUtama->id);
        $this->assertTrue($checkOver['is_overstock']);
        $this->assertSame(25, $checkOver['total_akan_menjadi']);
        $this->assertSame(20, $checkOver['stok_maksimum']);
        $this->assertStringContainsString('melebihi batas stok maksimum', $checkOver['pesan']);

        // Pembelian dalam satuan bertingkat: 1 Dus (10 Pcs) -> total 25 > 20 (overstock)
        $checkDus = PembelianForm::cekOverstockItem($barang->id, 'Dus', 1, $this->gudangUtama->id);
        $this->assertTrue($checkDus['is_overstock']);
        $this->assertSame(25, $checkDus['total_akan_menjadi']);
    }

    public function test_pembelian_form_get_overstock_items_from_state(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Sirup Karamel',
            'satuan' => 'Botol',
            'jenis_barang_id' => $this->kategori->id,
        ]);

        BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangUtama->id],
            ['stok' => 8, 'stok_minimum' => 2, 'stok_maksimum' => 10]
        );

        $state = [
            'gudang_id' => $this->gudangUtama->id,
            'details' => [
                [
                    'barang_id' => $barang->id,
                    'satuan' => 'Botol',
                    'jumlah' => 5, // 8 + 5 = 13 > 10
                ],
            ],
        ];

        $overstocks = PembelianForm::getOverstockItemsFromState($state);
        $this->assertCount(1, $overstocks);
        $this->assertSame($barang->nama_barang, $overstocks[0]['nama_barang']);
        $this->assertSame(13, $overstocks[0]['total_akan_menjadi']);
    }

    public function test_perpindahan_barang_form_mendeteksi_overstock_gudang_tujuan(): void
    {
        $barang = Barang::factory()->create([
            'nama_barang' => 'Susu Segar Bar',
            'satuan' => 'Kotak',
            'jenis_barang_id' => $this->kategori->id,
        ]);

        // Gudang Asal: Stok 50
        BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->gudangUtama->id],
            ['stok' => 50, 'stok_minimum' => 10, 'stok_maksimum' => 100]
        );

        // Gudang Tujuan (Bar/Chiller): Kapasitas cuma 6 kotak, stok saat ini 2 kotak
        BarangGudang::updateOrCreate(
            ['barang_id' => $barang->id, 'gudang_id' => $this->barChiller->id],
            ['stok' => 2, 'stok_minimum' => 1, 'stok_maksimum' => 6]
        );

        // Pindah 3 kotak: 2 + 3 = 5 <= 6 (aman)
        $checkAman = PerpindahanBarangForm::cekOverstockTujuan($barang->id, 'Kotak', 3, $this->barChiller->id);
        $this->assertFalse($checkAman['is_overstock']);

        // Pindah 10 kotak: 2 + 10 = 12 > 6 (overstock)
        $checkOver = PerpindahanBarangForm::cekOverstockTujuan($barang->id, 'Kotak', 10, $this->barChiller->id);
        $this->assertTrue($checkOver['is_overstock']);
        $this->assertSame(12, $checkOver['total_akan_menjadi']);
        $this->assertSame(6, $checkOver['stok_maksimum']);
        $this->assertStringContainsString('melebihi kapasitas batas stok maksimum', $checkOver['pesan']);

        $state = [
            'gudang_asal_id' => $this->gudangUtama->id,
            'gudang_tujuan_id' => $this->barChiller->id,
            'details' => [
                [
                    'barang_id' => $barang->id,
                    'satuan' => 'Kotak',
                    'jumlah' => 10,
                ],
            ],
        ];

        $overstocks = PerpindahanBarangForm::getOverstockItemsFromState($state);
        $this->assertCount(1, $overstocks);
        $this->assertSame(12, $overstocks[0]['total_akan_menjadi']);
    }

    public function test_kontrol_stok_gudang_dapat_memfilter_overstock(): void
    {
        $barangOver = Barang::factory()->create([
            'nama_barang' => 'Sirup Vanila Kelebihan',
            'satuan' => 'Botol',
            'jenis_barang_id' => $this->kategori->id,
        ]);

        $barangMenipis = Barang::factory()->create([
            'nama_barang' => 'Kopi Bubuk Menipis',
            'satuan' => 'Bungkus',
            'jenis_barang_id' => $this->kategori->id,
        ]);

        // Barang Over: stok 30 > maks 20
        $bgOver = BarangGudang::updateOrCreate(
            ['barang_id' => $barangOver->id, 'gudang_id' => $this->gudangUtama->id],
            ['stok' => 30, 'stok_minimum' => 5, 'stok_maksimum' => 20]
        );

        // Barang Menipis: stok 2 <= min 10
        $bgMenipis = BarangGudang::updateOrCreate(
            ['barang_id' => $barangMenipis->id, 'gudang_id' => $this->gudangUtama->id],
            ['stok' => 2, 'stok_minimum' => 10, 'stok_maksimum' => 50]
        );

        // Default query: hanya menampilkan defisit (Kopi Bubuk Menipis), bukan Sirup Vanila Kelebihan
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->assertCanSeeTableRecords([$bgMenipis])
            ->assertCanNotSeeTableRecords([$bgOver]);

        // Filter overstock: menampilkan Sirup Vanila Kelebihan
        Livewire::actingAs($this->user)
            ->test(KontrolStokGudang::class)
            ->set('data.status_stok', 'overstock')
            ->assertCanSeeTableRecords([$bgOver])
            ->assertCanNotSeeTableRecords([$bgMenipis]);
    }
}
