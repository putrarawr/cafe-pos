<?php

namespace Tests\Feature;

use App\Filament\Pages\RekapanAplikator;
use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Penjualan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RekapanAplikatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_rekapan_aplikator_page()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(RekapanAplikator::class)
            ->assertSuccessful();
    }

    public function test_rekapan_aplikator_aggregates_omset_komisi_dan_bersih()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Makanan', 'kode_jenis' => 'MKN', 'deskripsi' => 'Makanan']);

        $gofood = Aplikator::create(['nama_aplikator' => 'GoFood', 'kode_aplikator' => 'GOFOOD', 'persentase_komisi' => 20]);
        $grab = Aplikator::create(['nama_aplikator' => 'GrabFood', 'kode_aplikator' => 'GRABFOOD', 'persentase_komisi' => 15]);

        // neto di DB = nilai barang + ongkir; komisi = persen dari nilai barang (sebelum ongkir).
        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0001',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 100000,
            'diskon' => 0,
            'neto' => 115000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 115000,
            'kembalian' => 0,
            'biaya_kirim' => 15000,
            'aplikator_id' => $gofood->id,
            'komisi_aplikator' => 20000,
        ]);

        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Paket Nasi Goreng', 'harga_beli' => 5000, 'harga_jual' => 10000, 'satuan' => 'Porsi']);
        DetailJual::create(['penjualan_id' => $p1->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Porsi', 'jumlah' => 2, 'harga' => 10000, 'hpp' => 5000, 'diskon' => 0, 'subtotal' => 100000, 'is_bonus' => false]);

        Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0002',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 80000,
            'diskon' => 0,
            'neto' => 95000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 95000,
            'kembalian' => 0,
            'biaya_kirim' => 15000,
            'aplikator_id' => $grab->id,
            'komisi_aplikator' => 12000,
        ]);

        // Non-delivery: tidak boleh ikut terhitung di rekapan aplikator.
        Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0003',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 50000,
            'diskon' => 0,
            'neto' => 50000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 50000,
            'kembalian' => 0,
        ]);

        $page = Livewire::actingAs($user)->test(RekapanAplikator::class);

        $stats = $page->get('overviewStats');
        $this->assertSame(2, $stats['count_transaksi']);
        $this->assertSame(210000.0, $stats['sum_omset']);
        $this->assertSame(32000.0, $stats['sum_komisi']);
        $this->assertSame(30000.0, $stats['sum_kirim']);
        $this->assertSame(148000.0, $stats['sum_bersih']);

        $perAplikator = collect($stats['aplikators'])->keyBy('nama_aplikator');
        $this->assertSame(1, $perAplikator['GoFood']['count_transaksi']);
        $this->assertSame(115000.0, $perAplikator['GoFood']['sum_omset']);
        $this->assertSame(20000.0, $perAplikator['GoFood']['sum_komisi']);
        $this->assertSame(15000.0, $perAplikator['GoFood']['sum_kirim']);

        $page->assertSee('Rp 148.000')
            ->assertSee('GoFood')
            ->assertSee('GrabFood');
    }

    public function test_rekapan_aplikator_filter_aplikator_works()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);

        $gofood = Aplikator::create(['nama_aplikator' => 'GoFood', 'kode_aplikator' => 'GOFOOD', 'persentase_komisi' => 20]);
        $grab = Aplikator::create(['nama_aplikator' => 'GrabFood', 'kode_aplikator' => 'GRABFOOD', 'persentase_komisi' => 15]);

        $p1 = Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0004',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 12000,
            'diskon' => 0,
            'neto' => 15000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 15000,
            'kembalian' => 0,
            'biaya_kirim' => 3000,
            'aplikator_id' => $gofood->id,
            'komisi_aplikator' => 2400,
        ]);

        $p2 = Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0005',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 9000,
            'diskon' => 0,
            'neto' => 9000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 9000,
            'kembalian' => 0,
            'aplikator_id' => $grab->id,
            'komisi_aplikator' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(RekapanAplikator::class)
            ->set('data.aplikator_id', $gofood->id)
            ->assertCanSeeTableRecords([$gofood])
            ->assertCanNotSeeTableRecords([$grab]);

        $this->assertDatabaseHas('penjualan', ['id' => $p1->id]);
        $this->assertDatabaseHas('penjualan', ['id' => $p2->id]);
    }

    public function test_rekapan_aplikator_kartu_jadi_filter_interaktif()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);

        $gofood = Aplikator::create(['nama_aplikator' => 'GoFood', 'kode_aplikator' => 'GOFOOD', 'persentase_komisi' => 20]);
        $grab = Aplikator::create(['nama_aplikator' => 'GrabFood', 'kode_aplikator' => 'GRABFOOD', 'persentase_komisi' => 15]);

        Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0010',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 12000,
            'diskon' => 0,
            'neto' => 15000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 15000,
            'kembalian' => 0,
            'biaya_kirim' => 3000,
            'aplikator_id' => $gofood->id,
            'komisi_aplikator' => 2400,
        ]);

        Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0011',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 9000,
            'diskon' => 0,
            'neto' => 9000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 9000,
            'kembalian' => 0,
            'aplikator_id' => $grab->id,
            'komisi_aplikator' => 0,
        ]);

        $page = Livewire::actingAs($user)->test(RekapanAplikator::class);

        $page->call('pilihAplikator', $gofood->id)
            ->assertCanSeeTableRecords([$gofood])
            ->assertCanNotSeeTableRecords([$grab])
            ->assertSee('Menampilkan ringkasan terfilter')
            ->assertSeeHtml('wire:click="hapusFilterAplikator()"');

        $this->assertSame($gofood->id, $page->get('data.aplikator_id'));

        $page->call('hapusFilterAplikator')
            ->assertCanSeeTableRecords([$gofood, $grab])
            ->assertDontSee('Menampilkan ringkasan terfilter')
            ->assertSee('Total Pesanan Delivery');

        $this->assertNull($page->get('data.aplikator_id'));
    }

    public function test_rekapan_aplikator_record_action_modal_can_be_called()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $gofood = Aplikator::create(['nama_aplikator' => 'GoFood', 'kode_aplikator' => 'GOFOOD', 'persentase_komisi' => 20]);
        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Kopi Susu Gula Aren', 'harga_beli' => 8000, 'harga_jual' => 12000, 'satuan' => 'Cup']);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0006',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 24000,
            'diskon' => 0,
            'neto' => 28000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 28000,
            'kembalian' => 0,
            'biaya_kirim' => 4000,
            'aplikator_id' => $gofood->id,
            'komisi_aplikator' => 4800,
        ]);

        DetailJual::create(['penjualan_id' => $penjualan->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Cup', 'jumlah' => 2, 'harga' => 12000, 'hpp' => 8000, 'diskon' => 0, 'subtotal' => 24000, 'is_bonus' => false]);

        Livewire::actingAs($user)
            ->test(RekapanAplikator::class)
            ->callAction(TestAction::make('detail')->table($gofood));
    }

    public function test_rekapan_aplikator_modal_menampilkan_komisi_rata_rata()
    {
        $user = User::factory()->create();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Pusat', 'alamat' => 'Pusat']);
        $jenis = JenisBarang::create(['nama_jenis' => 'Minuman', 'kode_jenis' => 'MNM', 'deskripsi' => 'Minuman']);

        $gofood = Aplikator::create(['nama_aplikator' => 'GoFood', 'kode_aplikator' => 'GOFOOD', 'persentase_komisi' => 20]);
        $barang = Barang::create(['jenis_barang_id' => $jenis->id, 'nama_barang' => 'Espresso', 'harga_beli' => 9000, 'harga_jual' => 15000, 'satuan' => 'Cup']);

        // neto 33000 = barang 30000 + ongkir 3000; komisi master 20% dari 30000 = 5000.
        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260916-0007',
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal' => date('Y-m-d'),
            'total' => 30000,
            'diskon' => 0,
            'neto' => 33000,
            'jenis_pembayaran' => 'qris',
            'bayar' => 33000,
            'kembalian' => 0,
            'biaya_kirim' => 3000,
            'aplikator_id' => $gofood->id,
            'komisi_aplikator' => 5000,
        ]);

        DetailJual::create(['penjualan_id' => $penjualan->id, 'barang_id' => $barang->id, 'gudang_id' => $gudang->id, 'satuan' => 'Cup', 'jumlah' => 2, 'harga' => 15000, 'hpp' => 9000, 'diskon' => 0, 'subtotal' => 30000, 'is_bonus' => false]);

        $renderedView = view('filament.pages.modal-detail-rekapan-aplikator', [
            'aplikator' => $gofood,
            'dariTanggal' => date('Y-m-d'),
            'sampaiTanggal' => date('Y-m-d'),
            'invoices' => collect([$penjualan]),
        ])->render();

        // base (nilai barang) = 33000 - 3000 = 30000; komisi 5000/30000 = 16,7%
        $this->assertStringContainsString('Komisi master', $renderedView);
        $this->assertStringContainsString('Rata-rata komisi', $renderedView);
        $this->assertStringContainsString('>20%<', $renderedView);
        $this->assertStringContainsString('>16,7%<', $renderedView);
    }
}