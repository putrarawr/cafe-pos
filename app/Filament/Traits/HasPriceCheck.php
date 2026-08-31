<?php

namespace App\Filament\Traits;

trait HasPriceCheck
{
    protected function hasLowerSellingPrice(array $data): bool
    {
        $hargaBeli1 = (float) ($data['harga_beli'] ?? 0);
        $hargaJual1 = (float) ($data['harga_jual'] ?? 0);
        if ($hargaJual1 < $hargaBeli1) {
            return true;
        }

        for ($i = 2; $i <= 4; $i++) {
            $hbKey = "harga_beli_{$i}";
            $hjKey = "harga_jual_{$i}";
            if (isset($data[$hjKey]) && $data[$hjKey] !== null && $data[$hjKey] !== '') {
                $hj = (float) $data[$hjKey];
                $hb = (float) ($data[$hbKey] ?? 0);
                if ($hb > 0 && $hj < $hb) {
                    return true;
                }
            }
        }

        // Cek Tier Harga Bertingkat
        $tipe = $data['tipe_harga_bertingkat'] ?? 'persen';
        for ($t = 1; $t <= 3; $t++) {
            $qtyKey = "min_qty_{$t}";
            $nilaiKey = "nilai_tier_{$t}";
            if (!empty($data[$qtyKey]) && isset($data[$nilaiKey]) && (float)$data[$nilaiKey] > 0) {
                $nilai = (float) $data[$nilaiKey];
                $hargaTier = $tipe === 'persen'
                    ? $hargaJual1 * (1 - ($nilai / 100))
                    : $nilai;

                if ($hargaBeli1 > 0 && $hargaTier < $hargaBeli1) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getLowerPriceWarningMessage(array $data): string
    {
        $warnings = [];

        $hargaBeli1 = (float) ($data['harga_beli'] ?? 0);
        $hargaJual1 = (float) ($data['harga_jual'] ?? 0);
        if ($hargaJual1 < $hargaBeli1) {
            $diff = number_format($hargaBeli1 - $hargaJual1, 0, ',', '.');
            $hb = number_format($hargaBeli1, 0, ',', '.');
            $hj = number_format($hargaJual1, 0, ',', '.');
            $warnings[] = "• Level 1 (Dasar): Harga Jual (Rp {$hj}) < Harga Beli (Rp {$hb}) [Rugi Rp {$diff}]";
        }

        for ($i = 2; $i <= 4; $i++) {
            $hbKey = "harga_beli_{$i}";
            $hjKey = "harga_jual_{$i}";
            $satKey = "satuan_{$i}";
            if (isset($data[$hjKey]) && $data[$hjKey] !== null && $data[$hjKey] !== '') {
                $hj = (float) $data[$hjKey];
                $hb = (float) ($data[$hbKey] ?? 0);
                $sat = !empty($data[$satKey]) ? $data[$satKey] : "Level {$i}";
                if ($hb > 0 && $hj < $hb) {
                    $diff = number_format($hb - $hj, 0, ',', '.');
                    $hbFmt = number_format($hb, 0, ',', '.');
                    $hjFmt = number_format($hj, 0, ',', '.');
                    $warnings[] = "• {$sat} (Level {$i}): Harga Jual (Rp {$hjFmt}) < Harga Beli (Rp {$hbFmt}) [Rugi Rp {$diff}]";
                }
            }
        }

        // Cek Tier Harga Bertingkat
        $tipe = $data['tipe_harga_bertingkat'] ?? 'persen';
        for ($t = 1; $t <= 3; $t++) {
            $qtyKey = "min_qty_{$t}";
            $nilaiKey = "nilai_tier_{$t}";
            if (!empty($data[$qtyKey]) && isset($data[$nilaiKey]) && (float)$data[$nilaiKey] > 0) {
                $nilai = (float) $data[$nilaiKey];
                $minQty = (int) $data[$qtyKey];
                $hargaTier = $tipe === 'persen'
                    ? $hargaJual1 * (1 - ($nilai / 100))
                    : $nilai;

                if ($hargaBeli1 > 0 && $hargaTier < $hargaBeli1) {
                    $diff = number_format($hargaBeli1 - $hargaTier, 0, ',', '.');
                    $hbFmt = number_format($hargaBeli1, 0, ',', '.');
                    $hjFmt = number_format($hargaTier, 0, ',', '.');
                    $detailVal = $tipe === 'persen' ? "{$nilai}%" : "Rp " . number_format($nilai, 0, ',', '.');
                    $warnings[] = "• Harga Bertingkat Tier {$t} (Min Qty {$minQty}, Nilai: {$detailVal}): Harga Efektif (Rp {$hjFmt}) < Harga Beli (Rp {$hbFmt}) [Rugi Rp {$diff}]";
                }
            }
        }

        $msg = "Harga jual terdeteksi lebih rendah daripada harga beli:\n\n";
        $msg .= implode("\n", $warnings);
        $msg .= "\n\nApakah Anda yakin ingin tetap melanjutkan menyimpan data barang ini?";

        return $msg;
    }
}
