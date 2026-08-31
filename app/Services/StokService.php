<?php

namespace App\Services;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\HistoriHpp;
use App\Models\KartuStok;
use App\Models\Pembelian;
use App\Models\PerpindahanBarang;
use Illuminate\Support\Facades\DB;

class StokService
{
    public function tambahStok(int $barangId, int $gudangId, int $jumlah, array $konteks = []): void
    {
        $baris = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        if ($baris) {
            $saldoBaru = $baris->stok + $jumlah;
            DB::table('barang_gudang')->where('id', $baris->id)
                ->update(['stok' => $saldoBaru, 'updated_at' => now()]);
        } else {
            $saldoBaru = $jumlah;
            DB::table('barang_gudang')->insert([
                'barang_id' => $barangId,
                'gudang_id' => $gudangId,
                'stok' => $saldoBaru,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->catatKartu($barangId, $gudangId, $jumlah, $saldoBaru,
            $konteks['jenis'] ?? KartuStok::JENIS_MASUK, $konteks);
    }

    public function kurangiStok(int $barangId, int $gudangId, int $jumlah, array $konteks = [], bool $validasi = true): void
    {
        $baris = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        $stokSekarang = $baris?->stok ?? 0;

        if ($validasi && $stokSekarang < $jumlah) {
            $barang = Barang::find($barangId);
            $gudang = Gudang::find($gudangId);
            throw new StokTidakCukupException(
                "Stok {$barang->nama_barang} di {$gudang->nama_gudang} tidak cukup " .
                "(tersedia: {$stokSekarang}, dibutuhkan: {$jumlah})."
            );
        }

        $saldoBaru = $stokSekarang - $jumlah;
        if ($baris) {
            DB::table('barang_gudang')->where('id', $baris->id)
                ->update(['stok' => $saldoBaru, 'updated_at' => now()]);
        } else {
            DB::table('barang_gudang')->insert([
                'barang_id' => $barangId, 'gudang_id' => $gudangId,
                'stok' => $saldoBaru, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->catatKartu($barangId, $gudangId, -$jumlah, $saldoBaru,
            $konteks['jenis'] ?? KartuStok::JENIS_KELUAR, $konteks);
    }

    public function validasiDelta(array $deltas): void
    {
        foreach ($deltas as $kunci => $delta) {
            if ($delta >= 0) {
                continue;
            }
            [$barangId, $gudangId] = array_map('intval', explode(':', $kunci));
            $stok = (int) DB::table('barang_gudang')
                ->where('barang_id', $barangId)
                ->where('gudang_id', $gudangId)
                ->value('stok');
            if ($stok + $delta < 0) {
                $barang = Barang::find($barangId);
                $gudang = Gudang::find($gudangId);
                throw new StokTidakCukupException(
                    "Perubahan ini akan membuat stok {$barang->nama_barang} di " .
                    "{$gudang->nama_gudang} jadi minus (tersedia: {$stok}, perubahan: {$delta})."
                );
            }
        }
    }

    private function catatKartu(int $barangId, int $gudangId, int $jumlah, int $saldo, string $jenis, array $konteks): void
    {
        $rawTanggal = $konteks['tanggal'] ?? null;
        if ($rawTanggal) {
            $tanggal = (strlen((string) $rawTanggal) === 10)
                ? \Illuminate\Support\Carbon::parse($rawTanggal)->setTimeFrom(now())
                : $rawTanggal;
        } else {
            $tanggal = now();
        }

        $nomerSeri = $konteks['nomer_seri'] ?? null;
        if (!$nomerSeri) {
            $barang = Barang::find($barangId);
            $nomerSeri = $barang?->nomer_seri;
        }

        KartuStok::create([
            'barang_id' => $barangId,
            'nomer_seri' => $nomerSeri,
            'gudang_id' => $gudangId,
            'nomer_entry' => $konteks['nomer_entry'] ?? null,
            'tanggal' => $tanggal,
            'keterangan' => $konteks['keterangan'] ?? null,
            'jenis_transaksi' => $jenis,
            'jumlah' => $jumlah,
            'harga' => $konteks['harga'] ?? 0,
            'saldo' => $saldo,
        ]);
    }

    public function terapkanPembelian(Pembelian $pembelian): void
    {
        DB::transaction(function () use ($pembelian) {
            $pembelian->loadMissing('details.barang', 'supplier');
            foreach ($pembelian->details as $detail) {
                $barang = $detail->barang;
                $faktor = $barang ? $barang->getFaktorKonversi($detail->satuan) : 1;
                $jumlahDasar = $detail->jumlah * $faktor;

                if ($barang) {
                    $hargaBeliUnitMasuk = (float) round($detail->harga / max(1, $faktor));
                    $stokSebelumnya = (int) DB::table('barang_gudang')
                        ->where('barang_id', $detail->barang_id)
                        ->sum('stok');

                    $hppLama = (float) ($barang->hpp ?? $barang->harga_beli ?? 0);

                    if ($stokSebelumnya > 0) {
                        $hppBaru = (($stokSebelumnya * $hppLama) + ($jumlahDasar * $hargaBeliUnitMasuk)) / ($stokSebelumnya + $jumlahDasar);
                    } else {
                        $hppBaru = $hargaBeliUnitMasuk;
                    }

                    $barang->update([
                        'harga_beli' => (int) round($hargaBeliUnitMasuk),
                        'hpp' => round($hppBaru, 2),
                    ]);

                    HistoriHpp::create([
                        'barang_id' => $barang->id,
                        'pembelian_id' => $pembelian->id,
                        'supplier_id' => $pembelian->supplier_id,
                        'nomer_entry' => $pembelian->nomer_entry,
                        'tanggal' => $pembelian->tanggal ?? now(),
                        'stok_sebelum' => $stokSebelumnya,
                        'stok_sesudah' => $stokSebelumnya + $jumlahDasar,
                        'qty_beli' => $detail->jumlah,
                        'satuan' => $detail->satuan ?? $barang->satuan,
                        'harga_beli_masuk' => (float) $detail->harga,
                        'hpp_sebelum' => $hppLama,
                        'hpp_sesudah' => round($hppBaru, 2),
                        'keterangan' => 'Pembelian dari ' . ($pembelian->supplier->nama_supplier ?? '-'),
                    ]);
                }

                $this->tambahStok($detail->barang_id, $pembelian->gudang_id, $jumlahDasar, [
                    'nomer_entry' => $pembelian->nomer_entry,
                    'tanggal' => $pembelian->tanggal,
                    'harga' => $hargaBeliUnitMasuk ?? $detail->harga,
                    'keterangan' => 'Pembelian dari ' . ($pembelian->supplier->nama_supplier ?? '-') . ($detail->satuan && $barang && $detail->satuan !== $barang->satuan ? " (Beli: {$detail->jumlah} {$detail->satuan})" : ''),
                ]);
            }
        });
    }

    public function snapshotPembelian(Pembelian $pembelian): array
    {
        $pembelian->loadMissing('details.barang');

        return [
            'gudang_id' => $pembelian->gudang_id,
            'nomer_entry' => $pembelian->nomer_entry,
            'details' => $pembelian->details->map(function ($d) {
                $faktor = $d->barang ? $d->barang->getFaktorKonversi($d->satuan) : 1;

                return [
                    'barang_id' => $d->barang_id,
                    'jumlah' => $d->jumlah * $faktor,
                ];
            })->all(),
        ];
    }

    public function balikkanPembelian(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot['details'] as $detail) {
                $this->kurangiStok($detail['barang_id'], $snapshot['gudang_id'], $detail['jumlah'], [
                    'nomer_entry' => $snapshot['nomer_entry'],
                    'jenis' => KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan pembelian ' . $snapshot['nomer_entry'],
                ]);
            }
        });
    }

    public function terapkanPerpindahan(PerpindahanBarang $pindah): void
    {
        DB::transaction(function () use ($pindah) {
            $pindah->loadMissing('details.barang', 'gudangAsal', 'gudangTujuan');
            foreach ($pindah->details as $detail) {
                $barang = $detail->barang;
                $faktor = $barang ? $barang->getFaktorKonversi($detail->satuan ?? null) : 1;
                $jumlahDasar = $detail->jumlah * $faktor;

                $konteks = [
                    'nomer_entry' => $pindah->nomer_entry ?? 'PIN-' . $pindah->id,
                    'tanggal' => $pindah->tanggal,
                    'keterangan' => "Pindah {$pindah->gudangAsal->nama_gudang} → {$pindah->gudangTujuan->nama_gudang}" . ($detail->satuan ? " ({$detail->jumlah} {$detail->satuan})" : ''),
                ];
                $this->kurangiStok($detail->barang_id, $pindah->gudang_asal_id, $jumlahDasar,
                    $konteks + ['jenis' => KartuStok::JENIS_PINDAH_KELUAR]);
                $this->tambahStok($detail->barang_id, $pindah->gudang_tujuan_id, $jumlahDasar,
                    $konteks + ['jenis' => KartuStok::JENIS_PINDAH_MASUK]);
            }
        });
    }

    public function snapshotPerpindahan(PerpindahanBarang $pindah): array
    {
        $pindah->loadMissing('details.barang');

        return [
            'id' => $pindah->id,
            'nomer_entry' => $pindah->nomer_entry,
            'gudang_asal_id' => $pindah->gudang_asal_id,
            'gudang_tujuan_id' => $pindah->gudang_tujuan_id,
            'details' => $pindah->details->map(function ($d) {
                $faktor = $d->barang ? $d->barang->getFaktorKonversi($d->satuan ?? null) : 1;

                return [
                    'barang_id' => $d->barang_id,
                    'jumlah' => $d->jumlah * $faktor,
                ];
            })->all(),
        ];
    }

    public function balikkanPerpindahan(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot['details'] as $detail) {
                $nomerEntry = $snapshot['nomer_entry'] ?? 'PIN-' . $snapshot['id'];
                $konteks = [
                    'nomer_entry' => $nomerEntry,
                    'jenis' => KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan perpindahan ' . $nomerEntry,
                ];
                $this->kurangiStok($detail['barang_id'], $snapshot['gudang_tujuan_id'], $detail['jumlah'], $konteks);
                $this->tambahStok($detail['barang_id'], $snapshot['gudang_asal_id'], $detail['jumlah'], $konteks);
            }
        });
    }
}
