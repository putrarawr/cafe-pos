<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Meja;
use App\Models\OrderPending;
use App\Models\Penjualan;
use App\Models\SesiMeja;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PunyaMeja;
use Tests\TestCase;

/**
 * Meja = status pemakaian meja untuk pesanan dine in.
 *
 * Aturan yang dijaga tes ini:
 *  - meja WAJIB dipilih kalau ada item dine in
 *  - meja tidak relevan untuk take away / delivery
 *  - meja yang sedang terisi tidak bisa dipakai dua kali
 *  - meja bebas lagi setelah dibayar atau order pending dibatalkan
 *  - durasi dipakai untuk menandai overstay
 */
class MejaTest extends TestCase
{
    use PunyaMeja, RefreshDatabase;

    private User $user;

    private Gudang $gudang;

    private Barang $kopi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. Kasir 1']);
        $this->kopi = $this->buatKopi('Es Kopi Susu', 50);
    }

    private function buatKopi(string $nama, int $stok): Barang
    {
        $jenis = JenisBarang::create([
            'nama_jenis' => 'Minuman '.$nama,
            'kode_jenis' => 'MNM'.$nama,
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

        $barang->gudangs()->attach($this->gudang->id, ['stok' => $stok]);

        return $barang;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function payloadOrder(array $details, array $tambahan = []): array
    {
        return array_merge([
            'gudang_id' => $this->gudang->id,
            'tanggal' => date('Y-m-d'),
            'diskon' => 0,
            'diskon_persen' => 0,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 1000000,
            'details' => $details,
        ], $tambahan);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailDineIn(int $jumlah = 1, ?Barang $barang = null): array
    {
        return [
            'barang_id' => ($barang ?? $this->kopi)->id,
            'jumlah' => $jumlah,
            'satuan' => 'Cup',
            'diskon' => 0,
            'jenis_pesanan' => 'dine_in',
        ];
    }

    // =================================================================
    // Dashboard: daftar meja + status
    // =================================================================

    public function test_daftar_meja_menampilkan_semua_meja_yang_tersedia()
    {
        $meja = $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1', 'nama_meja' => 'Meja 1']);
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A2', 'nama_meja' => 'Meja 2']);

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $meja->id)
            ->assertJsonPath('items.0.kode_meja', 'A1')
            ->assertJsonPath('items.0.status', 'tersedia')
            ->assertJsonPath('items.0.sisa_menit', 60)
            ->assertJsonPath('items.0.overstay', false)
            ->assertJsonPath('items.0.order_pending_id', null);
    }

    public function test_daftar_meja_menandai_meja_terisi_dengan_sisa_waktu()
    {
        $meja = $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1', 'durasi_menit' => 90]);
        // Mulai 30 menit lalu → sisa 60 menit, belum overstay.
        $sesi = $this->sesiMejaTerisi($meja);
        $sesi->update(['mulai' => now()->subMinutes(30)]);

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('items.0.status', 'terisi')
            ->assertJsonPath('items.0.lama_menit', 30)
            ->assertJsonPath('items.0.sisa_menit', 60)
            ->assertJsonPath('items.0.overstay', false);
    }

    public function test_daftar_meja_menandai_overstay_kalau_durasi_sudah_lewat()
    {
        $meja = $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1', 'durasi_menit' => 60]);
        $sesi = $this->sesiMejaTerisi($meja);
        // Mulai 75 menit lalu → lewat 15 menit dari batas 60.
        $sesi->update(['mulai' => now()->subMinutes(75)]);

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('items.0.status', 'overstay')
            ->assertJsonPath('items.0.overstay', true)
            ->assertJsonPath('items.0.sisa_menit', 0)
            ->assertJsonPath('items.0.lama_menit', 75);
    }

    public function test_daftar_meja_menandai_meja_tidak_aktif_sebagai_tidak_aktif()
    {
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1', 'status_aktif' => false]);

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()->assertJsonPath('items.0.status', 'tidak_aktif');
    }

    public function test_daftar_meja_hanya_menampilkan_meja_dari_gudang_yang_diminta()
    {
        $gudangLain = Gudang::create(['nama_gudang' => 'Gudang Cabang', 'alamat' => 'Jl. Cabang 2']);
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1']);
        $this->buatMejaAktif($gudangLain, ['kode_meja' => 'B1']);

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $gudangLain->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.kode_meja', 'B1');
    }

    public function test_kode_meja_boleh_sama_antar_cabang_tapi_unik_di_dalam_satu_cabang()
    {
        $gudangLain = Gudang::create(['nama_gudang' => 'Gudang Cabang', 'alamat' => 'Jl. Cabang 2']);

        // Penamaan sederhana "Meja 01" diulang di setiap cabang.
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'Meja 01']);
        $this->buatMejaAktif($gudangLain, ['kode_meja' => 'Meja 01']);

        $this->assertSame(2, Meja::where('kode_meja', 'Meja 01')->count());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'Meja 01']);
    }

    // =================================================================
    // Guard: meja wajib untuk dine in
    // =================================================================

    public function test_bayar_dine_in_tanpa_meja_ditolak()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)])
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('meja', strtolower($response->json('message')));
        $this->assertSame(0, Penjualan::count());
    }

    public function test_tahan_order_dine_in_tanpa_meja_ditolak()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)])
        );

        $response->assertStatus(422);
        $this->assertSame(0, OrderPending::count());
        $this->assertSame(0, SesiMeja::count());
    }

    public function test_campuran_dine_in_dan_take_away_tanpa_meja_ditolak()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([
                $this->detailDineIn(1),
                [
                    'barang_id' => $this->kopi->id,
                    'jumlah' => 1,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ])
        );

        $response->assertStatus(422);
        $this->assertSame(0, Penjualan::count());
    }

    public function test_take_away_tanpa_meja_diterima_karena_meja_tidak_relevan()
    {
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([
                [
                    'barang_id' => $this->kopi->id,
                    'jumlah' => 2,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ])
        );

        $response->assertOk();
        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan);
        $this->assertNull($penjualan->meja_id);
    }

    public function test_meja_yang_dikirim_tanpa_item_dine_in_tidak_disimpan()
    {
        $meja = $this->mejaDefault($this->gudang);

        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([
                [
                    'barang_id' => $this->kopi->id,
                    'jumlah' => 2,
                    'satuan' => 'Cup',
                    'diskon' => 0,
                    'jenis_pesanan' => 'take_away',
                ],
            ], ['meja_id' => $meja->id])
        );

        $response->assertOk();
        $penjualan = Penjualan::first();
        // Meja tidak boleh bocor ke nota take away.
        $this->assertNull($penjualan->meja_id);
        $this->assertSame(0, SesiMeja::count());
    }

    public function test_meja_dari_gudang_lain_ditolak()
    {
        $gudangLain = Gudang::create(['nama_gudang' => 'Gudang Cabang', 'alamat' => 'Jl. Cabang 2']);
        $mejaLain = $this->buatMejaAktif($gudangLain, ['kode_meja' => 'B1']);

        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $mejaLain->id])
        );

        $response->assertStatus(422);
        $this->assertSame(0, Penjualan::count());
    }

    public function test_meja_tidak_aktif_ditolak()
    {
        $meja = $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1', 'status_aktif' => false]);

        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        );

        $response->assertStatus(422);
        $this->assertSame(0, Penjualan::count());
    }

    // =================================================================
    // Sesi meja: mengunci & melepas
    // =================================================================

    public function test_tahan_order_mengunci_meja_sampai_dibayar()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();
        $this->assertSame($meja->id, (int) $order->meja_id);

        $sesi = SesiMeja::first();
        $this->assertSame(SesiMeja::STATUS_TERISI, $sesi->status);
        $this->assertSame($meja->id, (int) $sesi->meja_id);
        $this->assertSame((int) $order->id, (int) $sesi->order_pending_id);
        $this->assertSame(60, (int) $sesi->batas_menit);
        $this->assertNull($sesi->selesai);
    }

    public function test_meja_yang_sudah_terisi_tidak_bisa_dipesan_lagi()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        // Kasir lain mencoba menahan order di meja yang sama.
        $response = $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('sedang dipakai', $response->json('message'));
        $this->assertSame(1, OrderPending::count());
        $this->assertSame(1, SesiMeja::count());
    }

    public function test_database_menolak_dua_sesi_terisi_di_meja_yang_sama()
    {
        $meja = $this->mejaDefault($this->gudang);
        $this->sesiMejaTerisi($meja);

        // Partial unique index harus menolak, walau check di aplikasi dilewati.
        $this->expectException(UniqueConstraintViolationException::class);

        SesiMeja::create([
            'meja_id' => $meja->id,
            'gudang_id' => $meja->gudang_id,
            'status' => SesiMeja::STATUS_TERISI,
            'mulai' => now(),
            'batas_menit' => 60,
        ]);
    }

    public function test_membayar_order_pending_melepas_meja_dan_mencatat_durasi()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();
        $sesi = SesiMeja::first();
        // Pelanggan sudah di meja selama 25 menit sebelum membayar.
        $sesi->update(['mulai' => now()->subMinutes(25)]);

        $response = $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], [
                'meja_id' => $meja->id,
                'order_pending_id' => $order->id,
                'bayar' => 36000,
            ])
        );

        $response->assertOk();

        $penjualan = Penjualan::first();
        $this->assertSame($meja->id, (int) $penjualan->meja_id);
        $this->assertSame(25, (int) $penjualan->durasi_meja_menit);

        $sesi = $sesi->fresh();
        $this->assertSame(SesiMeja::STATUS_SELESAI, $sesi->status);
        $this->assertSame((int) $penjualan->id, (int) $sesi->penjualan_id);
        $this->assertSame(25, (int) $sesi->durasi_menit_terpakai);
        $this->assertNotNull($sesi->selesai);
        $this->assertFalse((bool) $sesi->overstay);

        // Meja harus balik tersedia.
        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonPath('items.0.status', 'tersedia');
    }

    public function test_membayar_order_pending_yang_sudah_overstay_mencatat_overstay()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();
        // Lebih dari batas 60 menit → overstay.
        SesiMeja::first()->update(['mulai' => now()->subMinutes(80)]);

        $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], [
                'meja_id' => $meja->id,
                'order_pending_id' => $order->id,
                'bayar' => 36000,
            ])
        )->assertOk();

        $sesi = SesiMeja::first();
        $this->assertTrue((bool) $sesi->overstay);
        $this->assertSame(80, (int) $sesi->durasi_menit_terpakai);
    }

    public function test_batalnya_order_pending_juga_melepas_meja()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();

        $this->actingAs($this->user)
            ->postJson(route('kasir.order-pending.batal', $order->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $sesi = SesiMeja::first();
        $this->assertSame(SesiMeja::STATUS_BATAL, $sesi->status);
        $this->assertNotNull($sesi->selesai);

        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonPath('items.0.status', 'tersedia');
    }

    public function test_melepas_meja_manual_membuka_kunci_meja_yang_nyangkut()
    {
        $meja = $this->mejaDefault($this->gudang);
        // Sesi tanpa order pending: misalnya kasirnya tutup shift tanpa bayar.
        $sesi = $this->sesiMejaTerisi($meja);

        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonPath('items.0.status', 'terisi');

        $this->actingAs($this->user)
            ->postJson(route('kasir.meja.lepas', $sesi->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(SesiMeja::STATUS_SELESAI, $sesi->fresh()->status);
        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonPath('items.0.status', 'tersedia');
    }

    public function test_melepas_meja_ditolak_selagi_ada_order_pending()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $sesi = SesiMeja::first();

        // Order harus dibatalkan dulu supaya reservasi ikut lepas.
        $this->actingAs($this->user)
            ->postJson(route('kasir.meja.lepas', $sesi->id))
            ->assertStatus(422);
        $this->assertSame(SesiMeja::STATUS_TERISI, $sesi->fresh()->status);
    }

    public function test_melapas_meja_yang_sudah_terbuka_ditolak()
    {
        $meja = $this->mejaDefault($this->gudang);
        $sesi = $this->sesiMejaTerisi($meja);
        $sesi->update(['status' => SesiMeja::STATUS_SELESAI, 'selesai' => now()]);

        $this->actingAs($this->user)
            ->postJson(route('kasir.meja.lepas', $sesi->id))
            ->assertStatus(422);
    }

    public function test_meja_bisa_dipesan_lagi_setelah_pelanggan_pindah()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();
        $this->actingAs($this->user)
            ->postJson(route('kasir.order-pending.batal', $order->id))
            ->assertOk();

        // Meja sudah kosong, kasir baru boleh memakainya lagi.
        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertCreated();

        $this->assertSame(2, OrderPending::count());
        $this->assertSame(1, $this->jumlahMejaTerkunci($this->gudang->id));
    }

    public function test_daftar_order_pending_menampilkan_meja_dan_sisa_waktu()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $response = $this->actingAs($this->user)->getJson(route('kasir.order-pending.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.meja_id', $meja->id)
            ->assertJsonPath('items.0.meja_kode', $meja->kode_meja)
            ->assertJsonPath('items.0.sisa_menit', 60)
            ->assertJsonPath('items.0.overstay', false);
    }

    public function test_detail_order_pending_mengembalikan_meja_untuk_direstore_keranjang()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();

        $this->actingAs($this->user)
            ->getJson(route('kasir.order-pending.detail', $order->id))
            ->assertOk()
            ->assertJsonPath('meja_id', $meja->id)
            ->assertJsonPath('meja_kode', $meja->kode_meja);
    }

    public function test_meja_terpakai_ada_di_daftar_meja_sampai_dibayar()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();

        $response = $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('items.0.status', 'terisi')
            ->assertJsonPath('items.0.order_pending_id', (int) $order->id)
            ->assertJsonPath('items.0.kode_order', $order->kode_order)
            ->assertJsonPath('items.0.total', 36000);
    }

    public function test_bayar_dengan_meja_tidak_pernah_membuat_sesi_meja_yang_mengunci()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertOk();

        $penjualan = Penjualan::first();
        $this->assertNotNull($penjualan->meja_id);
        // Bayar langsung: meja dianggap bebas, jadi tidak ada sesi yang mengunci.
        $this->assertSame(0, SesiMeja::count());
    }

    public function test_riwayat_transaksi_menampilkan_meja_dan_durasi()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], ['meja_id' => $meja->id])
        )->assertCreated();

        $order = OrderPending::first();
        SesiMeja::first()->update(['mulai' => now()->subMinutes(40)]);

        $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(2)], [
                'meja_id' => $meja->id,
                'order_pending_id' => $order->id,
                'bayar' => 36000,
            ])
        )->assertOk();

        $response = $this->actingAs($this->user)->getJson(route('kasir.riwayat'));

        $response->assertOk();
        $item = collect($response->json('items'))->first();
        $this->assertSame($meja->id, $item['meja_id']);
        $this->assertSame($meja->kode_meja, $item['meja']);
        $this->assertSame(40, $item['durasi_meja_menit']);
    }

    public function test_sesi_meja_menyimpan_snapshot_batas_durasi_saat_dibuka()
    {
        $meja = $this->mejaDefault($this->gudang);

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertCreated();

        // Admin mengubah durasi master setelah sesi dibuka.
        Meja::whereKey($meja->id)->update(['durasi_menit' => 15]);

        $sesi = SesiMeja::first();
        $this->assertSame(60, (int) $sesi->batas_menit);
    }

    public function test_meja_boleh_dipakai_ulang_untuk_transaksi_lain_setelah_dibayar()
    {
        $meja = $this->mejaDefault($this->gudang);

        // Sesi pertama, dibayar langsung.
        $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertOk();

        // Meja masih hijau, jadi pesanan berikutnya boleh memakai meja sama.
        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonPath('items.0.status', 'tersedia');

        $this->actingAs($this->user)->postJson(
            route('kasir.order-pending.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $meja->id])
        )->assertCreated();

        $this->assertSame(1, Penjualan::count());
        $this->assertSame(1, OrderPending::count());
        $this->assertSame(1, SesiMeja::count());
    }

    public function test_semua_meja_terkunci_masih_bisa_dilihat_oleh_kasir_lain()
    {
        $meja = $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1']);
        $kasirLain = User::factory()->create();
        $sesi = $this->sesiMejaTerisi($meja);

        $response = $this->actingAs($kasirLain)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('items.0.status', 'terisi')
            ->assertJsonPath('items.0.sesi_id', (int) $sesi->id);
    }

    public function test_daftar_meja_menolak_gudang_yang_tidak_ada()
    {
        $this->actingAs($this->user)
            ->getJson(route('kasir.meja.index', ['gudang_id' => 999999]))
            ->assertStatus(422);
    }

    public function test_meja_hanya_berdampak_ke_satu_gudang_saat_pindah_gudang()
    {
        $gudangLain = Gudang::create(['nama_gudang' => 'Gudang Cabang', 'alamat' => 'Jl. Cabang 2']);
        $this->buatMejaAktif($this->gudang, ['kode_meja' => 'A1']);
        $this->buatMejaAktif($gudangLain, ['kode_meja' => 'B1']);

        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $this->gudang->id,
        ]))->assertOk()->assertJsonCount(1, 'items');

        $this->actingAs($this->user)->getJson(route('kasir.meja.index', [
            'gudang_id' => $gudangLain->id,
        ]))->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.kode_meja', 'B1');

        // Meja dari gudang lain tidak bisa dipakai di transaksi gudang ini.
        $mejaLain = Meja::where('kode_meja', 'B1')->first();
        $this->actingAs($this->user)->postJson(
            route('kasir.simpan'),
            $this->payloadOrder([$this->detailDineIn(1)], ['meja_id' => $mejaLain->id])
        )->assertStatus(422);
    }

    public function test_tablename_meja_benar_dan_tidak_menabrak_tabel_filament()
    {
        // Meja dipisah dari penjualan & order_pending supaya nomor nota tidak bergeser.
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('meja'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('sesi_meja'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('penjualan', 'meja_id'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('order_pending', 'meja_id'));
    }
}
