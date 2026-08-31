<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirRiwayatFilterTest extends TestCase
{
    use RefreshDatabase;

    private function buatPenjualan(array $overrides)
    {
        return Penjualan::create(array_merge([
            'nomer_nota' => 'PJ-TEST-' . uniqid(),
            'tanggal' => now()->toDateString(),
            'total' => 10000,
            'diskon' => 0,
            'neto' => 10000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 10000,
            'kembalian' => 0,
        ], $overrides));
    }

    public function test_riwayat_filter_by_metode_gudang_dan_kasir()
    {
        $user = User::factory()->create(['name' => 'Admin Utama']);
        $karyawan = Karyawan::create([
            'nama_karyawan' => 'Budi Santoso',
            'email' => 'budi@test.test',
            'password' => 'secret',
        ]);

        $gudangA = Gudang::create(['nama_gudang' => 'Gudang A', 'alamat' => 'Jl. A']);
        $gudangB = Gudang::create(['nama_gudang' => 'Gudang B', 'alamat' => 'Jl. B']);

        $this->buatPenjualan([
            'nomer_nota' => 'PJ-1',
            'gudang_id' => $gudangA->id,
            'karyawan_id' => $karyawan->id_karyawan,
            'jenis_pembayaran' => 'tunai',
        ]);
        $this->buatPenjualan([
            'nomer_nota' => 'PJ-2',
            'gudang_id' => $gudangB->id,
            'user_id' => $user->id,
            'jenis_pembayaran' => 'qris',
        ]);

        $tanggal = now()->toDateString();

        // Semua data
        $this->actingAs($user)->getJson("/kasir/riwayat?tanggal={$tanggal}")
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('summary.jumlah', 2)
            ->assertJsonPath('summary.total_neto', 20000);

        // Filter metode bayar
        $this->actingAs($user)->getJson("/kasir/riwayat?tanggal={$tanggal}&metode=qris")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.jumlah', 1)
            ->assertJsonPath('items.0.nomer_nota', 'PJ-2');

        // Filter gudang
        $this->actingAs($user)->getJson("/kasir/riwayat?tanggal={$tanggal}&gudang_id={$gudangA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.jumlah', 1)
            ->assertJsonPath('items.0.nomer_nota', 'PJ-1');

        // Filter kasir (karyawan)
        $this->actingAs($user)->getJson('/kasir/riwayat?tanggal=' . $tanggal . '&kasir=' . urlencode('Budi Santoso'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.jumlah', 1)
            ->assertJsonPath('items.0.nomer_nota', 'PJ-1');

        // Filter kasir (admin)
        $this->actingAs($user)->getJson('/kasir/riwayat?tanggal=' . $tanggal . '&kasir=' . urlencode('Admin Utama [Admin]'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.jumlah', 1)
            ->assertJsonPath('items.0.nomer_nota', 'PJ-2');

        // Kombinasi filter
        $this->actingAs($user)->getJson("/kasir/riwayat?tanggal={$tanggal}&gudang_id={$gudangB->id}&metode=qris")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.jumlah', 1)
            ->assertJsonPath('items.0.nomer_nota', 'PJ-2');
    }
}