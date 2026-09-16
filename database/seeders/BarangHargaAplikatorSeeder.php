<?php

namespace Database\Seeders;

use App\Models\Aplikator;
use App\Services\HargaAplikatorService;
use Illuminate\Database\Seeder;

class BarangHargaAplikatorSeeder extends Seeder
{
    /**
     * Isi harga jual khusus delivery untuk tiap aplikator platform,
     * dihitung dari harga jual Level 1 + persentase komisi aplikator.
     * Kurir Toko (TOKO) sengaja dilewati agar memakai harga normal.
     */
    public function run(): void
    {
        $service = app(HargaAplikatorService::class);

        $aplikators = Aplikator::query()
            ->where('status_aktif', true)
            ->whereIn('kode_aplikator', ['GOFOOD', 'GRABFOOD', 'SHOPEEFOOD', 'MAXIM'])
            ->get();

        foreach ($aplikators as $aplikator) {
            $jumlah = $service->sinkronkan($aplikator);
            $this->command?->info("Harga {$aplikator->nama_aplikator}: {$jumlah} barang.");
        }
    }
}
