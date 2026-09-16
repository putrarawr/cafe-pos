<?php

namespace App\Services;

use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\BarangHargaAplikator;
use Illuminate\Database\Eloquent\Builder;

class HargaAplikatorService
{
    /**
     * Hitung harga jual khusus aplikator dari harga Level 1 + persentase komisi.
     */
    public function hitungHarga(int $hargaJual, float $komisiPersen): int
    {
        return (int) round($hargaJual * (1 + ($komisiPersen / 100)));
    }

    /**
     * Query barang yang tampil di kasir (bukan kemasan / barang pembantu).
     */
    public function queryBarangBisaDijual(): Builder
    {
        return Barang::bisaDijual()
            ->where(function ($q) {
                $q->whereNull('tipe_barang')
                    ->orWhereNotIn('tipe_barang', ['kemasan', 'barang_pembantu']);
            });
    }

    /**
     * Sinkronkan harga seluruh barang bisa-dijual untuk satu aplikator
     * berdasarkan persentase komisi aplikator tersebut.
     *
     * @return int jumlah baris harga yang diperbarui
     */
    public function sinkronkan(Aplikator $aplikator): int
    {
        $barangs = $this->queryBarangBisaDijual()->get(['id', 'harga_jual']);
        if ($barangs->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($barangs as $barang) {
            $rows[] = [
                'barang_id' => $barang->id,
                'aplikator_id' => $aplikator->id,
                'harga_jual' => $this->hitungHarga((int) $barang->harga_jual, (float) $aplikator->persentase_komisi),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            BarangHargaAplikator::upsert(
                $chunk,
                ['barang_id', 'aplikator_id'],
                ['harga_jual', 'updated_at']
            );
        }

        return count($rows);
    }
}
