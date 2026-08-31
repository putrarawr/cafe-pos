<?php

namespace Tests\Feature;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PerpindahanBarang;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerpindahanStokTest extends TestCase
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

    public function test_edit_perpindahan_menyesuaikan_stok_dengan_benar(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create();
        $gudangA = Gudang::factory()->create();
        $gudangB = Gudang::factory()->create();

        // Modal stok awal di Gudang A: 20
        DB::transaction(fn () => $this->stok->tambahStok($barang->id, $gudangA->id, 20));

        // Buat perpindahan 15 dari A ke B
        $pindah = PerpindahanBarang::create([
            'gudang_asal_id' => $gudangA->id, 'gudang_tujuan_id' => $gudangB->id,
            'user_id' => $user->id, 'tanggal' => today(),
        ]);
        $pindah->details()->create(['barang_id' => $barang->id, 'jumlah' => 15]);

        $this->stok->terapkanPerpindahan($pindah);

        // Assert stok awal setelah perpindahan
        $this->assertSame(5, $this->stokDi($barang, $gudangA));
        $this->assertSame(15, $this->stokDi($barang, $gudangB));

        // Snapshot sebelum edit
        $snapshotLama = $this->stok->snapshotPerpindahan($pindah);

        // Edit jumlah menjadi 10
        $pindah->details()->first()->update(['jumlah' => 10]);

        // Simulasikan afterSave() di EditPerpindahanBarang
        DB::transaction(function () use ($snapshotLama, $pindah) {
            foreach ($snapshotLama['details'] as $detail) {
                $konteks = [
                    'nomer_entry' => 'PIN-' . $snapshotLama['id'],
                    'jenis' => \App\Models\KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan (edit) perpindahan PIN-' . $snapshotLama['id'],
                ];
                $this->stok->kurangiStok($detail['barang_id'], $snapshotLama['gudang_tujuan_id'], $detail['jumlah'], $konteks, validasi: false);
                $this->stok->tambahStok($detail['barang_id'], $snapshotLama['gudang_asal_id'], $detail['jumlah'], $konteks);
            }
            $this->stok->terapkanPerpindahan($pindah->fresh());
        });

        // Assert stok setelah edit (20 - 10 = 10 di A, 10 di B)
        $this->assertSame(10, $this->stokDi($barang, $gudangA));
        $this->assertSame(10, $this->stokDi($barang, $gudangB));
    }
}
