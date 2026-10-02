<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\OrderPending;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderPendingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Gudang $gudang;

    private Barang $kopi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $this->kopi = $this->buatKopi($this->gudang, 10);
    }

    private function buatKopi(Gudang $gudang, int $stok, string $nama = 'Es Kopi Susu'): Barang
    {
        $jenis = JenisBarang::create([
            'nama_jenis' => 'Minuman ' . $nama,
            'kode_jenis' => 'MNM' . $stok,
            'deskripsi' => 'Minuman',
        ]);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => $nama,
            'harga_beli' => 8000,
            'harga_jual' => 18000,
            'satuan' => 'Cup',
            'tipe_barang' => 'barang_jadi',
            'status' => 'tersedia',
            'bisa_dijual' => true,
        ]);

        $barang->gudangs()->attach($gudang->id, ['stok' => $stok]);

        return $barang;
    }

    private function stokFisik(): int
    {
        return (int) DB::table('barang_gudang')
            ->where('barang_id', $this->kopi->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('stok');
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function payloadOrder(array $details, array $tambahan = []): array
    {
        return array_merge([
            'gudang_id' => $this->gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 0,
            'details' => $details,
        ], $tambahan);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailBarang(int $jumlah, string $satuan = 'Cup'): array
    {
        return [
            'barang_id' => $this->kopi->id,
            'jumlah' => $jumlah,
            'satuan' => $satuan,
            'diskon' => 0,
            'jenis_pesanan' => 'dine_in',
        ];
    }

    public function test_simpan_order_pending_tahan_stok_tanpa_mengubah_stok_fisik()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(4)])
        );

        $response->assertCreated();

        $order = OrderPending::first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertStringStartsWith('ORD-' . date('Ymd') . '-', $order->kode_order);
        $this->assertEquals(72000, $order->total);

        // quantity order disimpan dalam satuan dasar
        $this->assertEquals(4, $order->items->first()->jumlah_dasar);

        // stok fisik TIDAK boleh berubah — yang ditahan cuma hak pakainya
        $this->assertEquals(10, $this->stokFisik());
        $this->assertEquals([$this->kopi->id => 4], app(StokService::class)->reservasiAktif($this->gudang->id));
    }

    public function test_order_pending_kedua_ditolak_kalau_melebihi_sisa_yang_masih_tersedia()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(7)])
        )->assertCreated();

        // sisa yang boleh dipesan = 10 fisik - 7 ditahan = 3
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(5)])
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('tidak cukup', $response->json('message'));

        $this->assertEquals(1, OrderPending::count());
        $this->assertEquals(10, $this->stokFisik(), 'Order yang gagal tidak boleh menyentuh stok fisik.');
    }

    public function test_pembatalan_melepas_reservasi()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(7)])
        )->assertCreated();

        $order = OrderPending::first();

        $this->actingAs($this->user)
            ->postJson(route('kasir.order-pending.batal', $order->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('batal', $order->fresh()->status);
        $this->assertEquals([], app(StokService::class)->reservasiAktif($this->gudang->id));
        $this->assertEquals(10, $this->stokFisik());

        // setelah dibatalkan, order baru dengan qty penuh harus bisa masuk
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(10)])
        )->assertCreated();
    }

    public function test_order_yang_dibayar_melepas_reservasi_dan_mengurangi_stok_fisik()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(4)])
        )->assertCreated();

        $order = OrderPending::first();

        $response = $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(4)],
            ['bayar' => 72000, 'order_pending_id' => $order->id]
        ));

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(72000, $penjualan->neto);

        $order->refresh();
        $this->assertEquals('selesai', $order->status);
        $this->assertEquals($penjualan->id, $order->penjualan_id);

        // stok fisik baru berkurang setelah dibayar
        $this->assertEquals(6, $this->stokFisik());
        $this->assertEquals([], app(StokService::class)->reservasiAktif($this->gudang->id));
    }

    public function test_order_yang_dilanjutkan_boleh_memakai_reservasinya_sendiri()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(9)])
        )->assertCreated();

        $order = OrderPending::first();

        // Order ini Divingkatkan sedikit (9 -> 10) lalu dibayar.
        // Kalau reservasinya sendiri ikut dipotong, sisa hanya 1 dan ini akan 422.
        $response = $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(10)],
            ['bayar' => 180000, 'order_pending_id' => $order->id]
        ));

        $response->assertOk();
        $this->assertEquals(0, $this->stokFisik());
    }

    public function test_transaksi_biasa_ikut_memotong_stok_yang_sudah_dipesan_order_lain()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(7)])
        )->assertCreated();

        // hanya 3 yang tersisa untuk kasir lain
        $response = $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(5)],
            ['bayar' => 90000]
        ));

        $response->assertStatus(422);
        $this->assertStringContainsString('dipesan di order lain', $response->json('message'));
        $this->assertNull(Penjualan::first());
        $this->assertEquals(10, $this->stokFisik());
    }

    public function test_order_batal_dan_selesai_tidak_ikut_mengurangi_stok_tersedia()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(6)])
        )->assertCreated();

        $order = OrderPending::first();
        $order->update(['status' => OrderPending::STATUS_BATAL]);

        $this->assertEquals([], app(StokService::class)->reservasiAktif($this->gudang->id));

        // stok 10 kembali utuh untuk kasir berikutnya
        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(10)],
            ['bayar' => 180000]
        ))->assertOk();
    }

    public function test_daftar_order_pending_menampilkan_order_aktif_saja()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(2)])
        )->assertCreated();

        $batal = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(3)])
        )->assertCreated()->json();

        $this->actingAs($this->user)->postJson(route('kasir.order-pending.batal', $batal['id']))->assertOk();

        $response = $this->actingAs($this->user)->getJson(route('kasir.order-pending.index'));
        $response->assertOk();
        $this->assertCount(1, $response->json('items'));
        $this->assertEquals(2, $response->json('items.0.items.0.jumlah'));
    }

    public function test_endpoint_kasir_data_mengekspos_stok_reservasi_dan_tersedia()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(6)])
        )->assertCreated();

        $response = $this->actingAs($this->user)->getJson(route('kasir.data'));
        $response->assertOk();

        $barang = collect($response->json('barang'))->firstWhere('id', $this->kopi->id);
        $this->assertNotNull($barang);
        $this->assertEquals(10, $barang['stok'][$this->gudang->id], 'stok fisik tidak boleh ikut ter-reserve');
        $this->assertEquals(6, $barang['stok_reservasi'][$this->gudang->id]);
        $this->assertEquals(4, $barang['stok_tersedia'][$this->gudang->id]);
    }

    public function test_reservasi_dihitung_dalam_satuan_dasar_untuk_satuan_bertingkat()
    {
        // stok dinaikkan supaya 1 Box (12 Cup) muat, gudang default cuma 10
        DB::table('barang_gudang')
            ->where('barang_id', $this->kopi->id)
            ->where('gudang_id', $this->gudang->id)
            ->update(['stok' => 50]);

        // 1 Box = 12 Cup
        Barang::whereKey($this->kopi->id)->update([
            'satuan_2' => 'Box',
            'isi_satuan_2' => 12,
            'harga_jual_2' => 200000,
        ]);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(1, 'Box')])
        )->assertCreated();

        // 1 Box harus mengunci 12 Cup, bukan 1
        $this->assertEquals(
            [$this->kopi->id => 12],
            app(StokService::class)->reservasiAktif($this->gudang->id)
        );
        $this->assertEquals(12, OrderPending::first()->items->first()->jumlah_dasar);

        // sisa yang boleh dipesan = 50 - 12 = 38 Cup
        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(38)],
            ['bayar' => 684000]
        ))->assertOk();
    }

    public function test_order_pending_menolak_item_delivery_tanpa_aplikator()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([[
                'barang_id' => $this->kopi->id,
                'jumlah' => 1,
                'satuan' => 'Cup',
                'diskon' => 0,
                'jenis_pesanan' => 'delivery',
            ]])
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('aplikator', $response->json('message'));
    }

    public function test_nomor_order_pending_berurut_dan_tidak_menggeser_nomor_nota()
    {
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(1)])
        )->assertCreated();

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(1)])
        )->assertCreated();

        $kode = OrderPending::orderBy('id')->pluck('kode_order')->all();
        $this->assertStringEndsWith('-0001', $kode[0]);
        $this->assertStringEndsWith('-0002', $kode[1]);

        // nota penjualan tetap mulai dari 0001 walau sudah ada 2 order pending
        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(1)],
            ['bayar' => 18000]
        ))->assertOk();

        $this->assertStringEndsWith('-0001', Penjualan::first()->nomer_nota);
    }

    public function test_order_yang_sudah_dibatalkan_tidak_bisa_dilanjutkan_lagi()
    {
        $id = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(2)])
        )->assertCreated()->json('id');

        $this->actingAs($this->user)->postJson(route('kasir.order-pending.batal', $id))
            ->assertOk();

        // kasir yang masih punya tab terbuka akan gagal waktu mau lanjutin
        $this->actingAs($this->user)->getJson(route('kasir.order-pending.index'))
            ->assertOk()
            ->assertJsonPath('items', []);

        $this->actingAs($this->user)->getJson(route('kasir.order-pending.detail', $id))
            ->assertStatus(409);
    }

    public function test_membayar_order_yang_sudah_dibatalkan_ditolak()
    {
        $res = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(2)])
        )->assertCreated();

        $id = $res->json('id');

        $this->actingAs($this->user)->postJson(route('kasir.order-pending.batal', $id))
            ->assertOk();

        // pembayaran ditolak, tidak boleh ada penjualan yang terbentuk
        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(2)],
            ['bayar' => 36000, 'order_pending_id' => $id]
        ))->assertStatus(422);

        $this->assertSame(0, Penjualan::count());
        $this->assertSame(OrderPending::STATUS_BATAL, OrderPending::find($id)->status);
    }

    public function test_membayar_order_yang_sudah_selesai_tidak_bisa_dipakai_lagi()
    {
        $id = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(2)])
        )->assertCreated()->json('id');

        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(2)],
            ['bayar' => 36000, 'order_pending_id' => $id]
        ))->assertOk();

        // order yang sama tidak bisa dibayar dua kali
        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(2)],
            ['bayar' => 36000, 'order_pending_id' => $id]
        ))->assertStatus(422);

        $this->assertSame(1, Penjualan::count());
    }

    public function test_order_pending_dari_gudang_lain_ditolak_saat_dibayar()
    {
        $gudangLain = Gudang::create(['nama_gudang' => 'Gudang B', 'kode_gudang' => 'GB']);

        $id = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailBarang(2)])
        )->assertCreated()->json('id');

        $this->actingAs($this->user)->postJson(route('kasir.simpan'), $this->payloadOrder(
            [$this->detailBarang(2)],
            ['bayar' => 36000, 'order_pending_id' => $id, 'gudang_id' => $gudangLain->id]
        ))->assertStatus(422);

        $this->assertSame(0, Penjualan::count());
    }
}
