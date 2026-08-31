<?php

namespace Tests\Feature;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Pembelian;
use App\Models\PerpindahanBarang;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StokServiceTest extends TestCase
{
    use RefreshDatabase;

    private StokService $stok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stok = app(StokService::class);
    }

    private function stokDi(Barang $barang, Gudang $gudang): int
    {
        return (int) DB::table('barang_gudang')
            ->where('barang_id', $barang->id)
            ->where('gudang_id', $gudang->id)
            ->value('stok');
    }

    public function test_tambah_stok_membuat_baris_pivot_dan_kartu_stok(): void
    {
        $barang = Barang::factory()->create();
        $gudang = Gudang::factory()->create();

        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $gudang->id, 10, [
            'nomer_entry' => 'TEST-1',
            'harga' => 5000,
        ]));

        $this->assertSame(10, $this->stokDi($barang, $gudang));
        $kartu = KartuStok::sole();
        $this->assertSame('masuk', $kartu->jenis_transaksi);
        $this->assertSame(10, $kartu->saldo);
        $this->assertSame($barang->nomer_seri, $kartu->nomer_seri);
    }

    public function test_kurangi_stok_melempar_exception_kalau_tidak_cukup(): void
    {
        $barang = Barang::factory()->create();
        $gudang = Gudang::factory()->create();
        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $gudang->id, 5));

        $this->expectException(StokTidakCukupException::class);
        DB::transaction(fn () => $this->stok->kurangiStok($barang->id, $gudang->id, 10));
    }

    public function test_validasi_delta_melempar_exception_untuk_delta_negatif_melebihi_stok(): void
    {
        $barang = Barang::factory()->create();
        $gudang = Gudang::factory()->create();
        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $gudang->id, 5));

        $this->expectException(StokTidakCukupException::class);
        $this->stok->validasiDelta(["{$barang->id}:{$gudang->id}" => -6]);
    }

    public function test_terapkan_dan_balikkan_pembelian_konsisten(): void
    {
        $user = \App\Models\User::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        $gudang = Gudang::factory()->create();
        $barang = Barang::factory()->create(['harga_beli' => 1000]);

        $pembelian = Pembelian::create([
            'nomer_entry' => 'PB-001', 'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id, 'user_id' => $user->id,
            'tanggal' => today(), 'total' => 50000, 'neto' => 50000,
        ]);
        $pembelian->details()->create([
            'barang_id' => $barang->id, 'jumlah' => 10, 'harga' => 5000, 'subtotal' => 50000,
        ]);

        $this->stok->terapkanPembelian($pembelian);

        $this->assertSame(10, $this->stokDi($barang, $gudang));
        // harga master ikut harga terakhir
        $this->assertSame(5000, $barang->fresh()->harga_beli);

        $this->stok->balikkanPembelian($this->stok->snapshotPembelian($pembelian));
        $this->assertSame(0, $this->stokDi($barang, $gudang));
        // ledger: 1 entri masuk + 1 entri koreksi
        $this->assertSame(2, KartuStok::count());
        $this->assertSame('koreksi', KartuStok::latest('id')->first()->jenis_transaksi);
    }

    public function test_terapkan_perpindahan_memindahkan_stok(): void
    {
        $user = \App\Models\User::factory()->create();
        $barang = Barang::factory()->create();
        $asal = Gudang::factory()->create();
        $tujuan = Gudang::factory()->create();
        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $asal->id, 20));

        $pindah = PerpindahanBarang::create([
            'gudang_asal_id' => $asal->id, 'gudang_tujuan_id' => $tujuan->id,
            'user_id' => $user->id, 'tanggal' => today(),
        ]);
        $pindah->details()->create(['barang_id' => $barang->id, 'jumlah' => 15]);

        $this->stok->terapkanPerpindahan($pindah);

        $this->assertSame(5, $this->stokDi($barang, $asal));
        $this->assertSame(15, $this->stokDi($barang, $tujuan));
        $this->assertSame(1, KartuStok::where('jenis_transaksi', 'pindah_keluar')->count());
        $this->assertSame(1, KartuStok::where('jenis_transaksi', 'pindah_masuk')->count());

        $this->stok->balikkanPerpindahan($this->stok->snapshotPerpindahan($pindah));
        $this->assertSame(20, $this->stokDi($barang, $asal));
        $this->assertSame(0, $this->stokDi($barang, $tujuan));
    }

    public function test_perpindahan_melebihi_stok_ditolak_dan_tidak_mengubah_apapun(): void
    {
        $user = \App\Models\User::factory()->create();
        $barang = Barang::factory()->create();
        $asal = Gudang::factory()->create();
        $tujuan = Gudang::factory()->create();
        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $asal->id, 5));
        $jumlahKartuAwal = KartuStok::count();

        $pindah = PerpindahanBarang::create([
            'gudang_asal_id' => $asal->id, 'gudang_tujuan_id' => $tujuan->id,
            'user_id' => $user->id, 'tanggal' => today(),
        ]);
        $pindah->details()->create(['barang_id' => $barang->id, 'jumlah' => 10]);

        try {
            $this->stok->terapkanPerpindahan($pindah);
            $this->fail('Seharusnya melempar StokTidakCukupException');
        } catch (StokTidakCukupException) {
        }

        // rollback penuh: stok utuh, tidak ada entri kartu baru
        $this->assertSame(5, $this->stokDi($barang, $asal));
        $this->assertSame($jumlahKartuAwal, KartuStok::count());
    }
}
