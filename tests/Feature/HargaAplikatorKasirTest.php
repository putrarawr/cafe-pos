<?php

namespace Tests\Feature;

use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\BarangHargaAplikator;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaMeja;
use Tests\TestCase;

class HargaAplikatorKasirTest extends TestCase
{
    use RefreshDatabase, PunyaMeja;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
    }

    private function seedMasterData(): array
    {
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Test No.1']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'deskripsi' => 'Minuman dingin & panas']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Test',
            'harga_jual' => 10000,
            'harga_beli' => 5000,
            'satuan' => 'Pcs',
            'status' => 'tersedia',
            'bisa_dijual' => true,
            'tipe_harga_bertingkat' => 'persen',
        ]);
        $barang->gudangs()->attach($gudang->id, ['stok' => 100]);

        $aplikatorGoFood = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20,
            'status_aktif' => true,
        ]);

        $aplikatorGrab = Aplikator::create([
            'nama_aplikator' => 'GrabFood',
            'kode_aplikator' => 'GRABFOOD',
            'persentase_komisi' => 15,
            'status_aktif' => true,
        ]);

        BarangHargaAplikator::create([
            'barang_id' => $barang->id,
            'aplikator_id' => $aplikatorGoFood->id,
            'harga_jual' => 12000, // harga khusus GoFood
        ]);

        // GrabFood: sengaja TIDAK dibuat agar test fallback harga normal

        return compact('gudang', 'barang', 'aplikatorGoFood', 'aplikatorGrab');
    }

    private function simpanPayload(array $master, int $aplikatorId, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/kasir/simpan', array_merge([
            'gudang_id' => $master['gudang']->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 100000,
            'aplikator_id' => $aplikatorId,
            'details' => [[
                'barang_id' => $master['barang']->id,
                'jumlah' => 2,
                'diskon' => 0,
                'satuan' => 'Pcs',
                'jenis_pesanan' => 'delivery',
            ]],
        ], $extra));
    }

    public function test_kasir_simpan_delivery_dengan_master_harga_aplikator(): void
    {
        $this->loginAsAdmin();
        $master = $this->seedMasterData();

        $this->simpanPayload($master, $master['aplikatorGoFood']->id)
            ->assertOk()
            ;

        $detail = \App\Models\DetailJual::first();
        // Master 12000; 2 pcs; tier kosong → harga normal = 12000, diskon = 0
        $this->assertSame(12000, $detail->harga);
        $this->assertSame(24000, $detail->subtotal);

        $penjualan = $detail->penjualan;
        // komisi_aplikator: 20% dari neto (24000) = 4800
        $this->assertSame(4800, $penjualan->komisi_aplikator);
        $this->assertSame($master['aplikatorGoFood']->id, $penjualan->aplikator_id);
    }

    public function test_kasir_simpan_delivery_tanpa_master_menggunakan_harga_normal(): void
    {
        $this->loginAsAdmin();
        $master = $this->seedMasterData();

        // GrabFood tidak punya master harga → fallback harga_jual barang (10000)
        $this->simpanPayload($master, $master['aplikatorGrab']->id)
            ->assertOk()
            ;

        $detail = \App\Models\DetailJual::first();
        $this->assertSame(10000, $detail->harga);
        $this->assertSame(20000, $detail->subtotal);

        // komisi_aplikator: 15% dari neto 20000 = 3000
        $this->assertSame(3000, $detail->penjualan->komisi_aplikator);
    }

    public function test_kasir_item_take_away_tidak_terpengaruh_harga_aplikator(): void
    {
        $this->loginAsAdmin();
        $master = $this->seedMasterData();

        // Simpan transaksi delivery + take_away bersamaan; hanya delivery dipengaruhi
        $this->postJson('/kasir/simpan', [
            'gudang_id' => $master['gudang']->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 100000,
            'aplikator_id' => $master['aplikatorGoFood']->id,
            'details' => [
                ['barang_id' => $master['barang']->id, 'jumlah' => 1, 'diskon' => 0, 'satuan' => 'Pcs', 'jenis_pesanan' => 'delivery'],
                ['barang_id' => $master['barang']->id, 'jumlah' => 3, 'diskon' => 0, 'satuan' => 'Pcs', 'jenis_pesanan' => 'take_away'],
            ],
        ])->assertOk();

        $details = \App\Models\DetailJual::orderBy('id')->get();
        $this->assertCount(2, $details);

        $delivery = $details->firstWhere('jenis_pesanan', 'delivery');
        $takeAway = $details->firstWhere('jenis_pesanan', 'take_away');

        $this->assertSame(12000, $delivery->harga); // harga aplikator
        $this->assertSame(10000, $takeAway->harga); // harga normal
    }

    public function test_kasir_data_menyertakan_harga_aplikator(): void
    {
        $this->loginAsAdmin();
        $master = $this->seedMasterData();

        // Ekstrak harga_aplikator dari view (kasirData JSON)
        $response = $this->get('/kasir');
        $response->assertOk();
        $html = $response->getContent();
        preg_match('/window\.KASIR_DATA\s*=\s*(\{.*?\});/s', $html, $m);
        $this->assertNotEmpty($m, 'KASIR_DATA not found in response');
        $data = json_decode($m[1], true);

        $kopi = collect($data['barang'])->firstWhere('id', $master['barang']->id);
        $this->assertNotNull($kopi);
        $this->assertArrayHasKey('harga_aplikator', $kopi);
        $this->assertSame(12000, $kopi['harga_aplikator'][$master['aplikatorGoFood']->id] ?? null);
    }

    public function test_komisi_tidak_disimpan_jika_tidak_ada_aplikator(): void
    {
        $this->loginAsAdmin();
        $master = $this->seedMasterData();

        // Tanpa aplikator_id → komisi_aplikator null
        $this->postJson('/kasir/simpan', $this->denganMeja([
            'gudang_id' => $master['gudang']->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 100000,
            'aplikator_id' => null,
            'details' => [['barang_id' => $master['barang']->id, 'jumlah' => 1, 'diskon' => 0, 'satuan' => 'Pcs', 'jenis_pesanan' => 'dine_in']],
        ]))->assertOk();

        $this->assertNull(\App\Models\Penjualan::first()->komisi_aplikator);
    }

    public function test_harga_aplikator_dengan_harga_bertingkat_persen(): void
    {
        $this->loginAsAdmin();

        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Test']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'deskripsi' => 'Minuman dingin & panas']);
        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Botol',
            'harga_jual' => 10000,
            'harga_beli' => 5000,
            'satuan' => 'Pcs',
            'status' => 'tersedia',
            'bisa_dijual' => true,
            'tipe_harga_bertingkat' => 'persen',
            'min_qty_1' => 10,
            'nilai_tier_1' => 10, // potongan 10% jika beli ≥10
        ]);
        $barang->gudangs()->attach($gudang->id, ['stok' => 200]);

        $aplikator = Aplikator::create([
            'nama_aplikator' => 'GoFood',
            'kode_aplikator' => 'GOFOOD',
            'persentase_komisi' => 20,
            'status_aktif' => true,
        ]);

        // Master harga aplikator = 12000, tier berlaku
        BarangHargaAplikator::create([
            'barang_id' => $barang->id,
            'aplikator_id' => $aplikator->id,
            'harga_jual' => 12000,
        ]);

        $this->postJson('/kasir/simpan', [
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'diskon' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 200000,
            'aplikator_id' => $aplikator->id,
            'details' => [['barang_id' => $barang->id, 'jumlah' => 15, 'diskon' => 0, 'satuan' => 'Pcs', 'jenis_pesanan' => 'delivery']],
        ])->assertOk();

        $detail = \App\Models\DetailJual::first();
        // harga_jual = 12000; qty=15 ≥ min_qty_1(10) → tier 10% → efektif = 12000*0.9 = 10800
        $this->assertSame(12000, $detail->harga);
        $this->assertSame(18000, $detail->diskon);
        $this->assertSame(15 * 10800, $detail->subtotal);

        // komisi_aplikator: 20% dari neto (15*10800=162000) = 32400
        $this->assertSame(32400, $detail->penjualan->komisi_aplikator);
    }
}
