<?php

namespace Tests\Feature;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembelianStokTest extends TestCase
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

    public function test_hapus_pembelian_setelah_stok_dipindah_keluar_ditolak(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $gudangA = Gudang::factory()->create();
        $gudangB = Gudang::factory()->create();
        $barang = Barang::factory()->create();

        // Beli 10 ke Gudang A
        $pembelian = Pembelian::create([
            'nomer_entry' => 'PB-001', 'supplier_id' => $supplier->id,
            'gudang_id' => $gudangA->id, 'user_id' => $user->id,
            'tanggal' => today(), 'total' => 50000, 'neto' => 50000,
        ]);
        $pembelian->details()->create([
            'barang_id' => $barang->id, 'jumlah' => 10, 'harga' => 5000, 'subtotal' => 50000,
        ]);
        $this->stok->terapkanPembelian($pembelian);

        $this->assertSame(10, $this->stokDi($barang, $gudangA));

        // Pindah 8 ke Gudang B
        DB::transaction(function () use ($barang, $gudangA, $gudangB) {
            $this->stok->kurangiStok($barang->id, $gudangA->id, 8);
            $this->stok->tambahStok($barang->id, $gudangB->id, 8);
        });

        $this->assertSame(2, $this->stokDi($barang, $gudangA));
        $this->assertSame(8, $this->stokDi($barang, $gudangB));

        // Delete pembelian ditolak karena gudang A stoknya sisa 2 (batal pembelian 10 butuh 10)
        $this->expectException(StokTidakCukupException::class);
        $this->stok->balikkanPembelian($this->stok->snapshotPembelian($pembelian));
    }
}
