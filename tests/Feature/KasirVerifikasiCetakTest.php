<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Karyawan;
use App\Models\Penjualan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirVerifikasiCetakTest extends TestCase
{
    use RefreshDatabase;

    private function buatKaryawan(string $nama, string $password): Karyawan
    {
        return Karyawan::create([
            'nama_karyawan' => $nama,
            'email' => strtolower($nama) . '@test.test',
            'password' => $password,
        ]);
    }

    public function test_cetak_ulang_butuh_password_user_yang_login()
    {
        $karyawan = $this->buatKaryawan('Siti Kasir', 'rahasia123');
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Utama', 'alamat' => 'Jl. A']);

        $penjualan = Penjualan::create([
            'nomer_nota' => 'PJ-20260820-0001',
            'karyawan_id' => $karyawan->id_karyawan,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'total' => 10000,
            'diskon' => 0,
            'neto' => 10000,
            'jenis_pembayaran' => 'tunai',
            'bayar' => 10000,
            'kembalian' => 0,
        ]);

        // Tanpa password ditolak
        $this->actingAs($karyawan, 'karyawan')
            ->postJson("/kasir/riwayat/{$penjualan->id}/cetak")
            ->assertStatus(422);

        // Password salah ditolak
        $this->actingAs($karyawan, 'karyawan')
            ->postJson("/kasir/riwayat/{$penjualan->id}/cetak", ['password' => 'salah'])
            ->assertStatus(422);

        // Password benar mengembalikan data nota
        $this->actingAs($karyawan, 'karyawan')
            ->postJson("/kasir/riwayat/{$penjualan->id}/cetak", ['password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('nomer_nota', 'PJ-20260820-0001')
            ->assertJsonPath('nama_kasir', 'Siti Kasir');
    }

    public function test_cetak_ulang_nota_yang_tidak_ada_ditolak()
    {
        $karyawan = $this->buatKaryawan('Budi', 'rahasia123');

        $this->actingAs($karyawan, 'karyawan')
            ->postJson('/kasir/riwayat/99999/cetak', ['password' => 'rahasia123'])
            ->assertStatus(404);
    }
}