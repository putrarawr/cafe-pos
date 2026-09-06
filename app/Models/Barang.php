<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Barang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barang';

    protected $guarded = ['id'];

    protected $attributes = [
        'tipe_barang' => 'barang_dagang',
        'status' => 'tersedia',
        'bisa_dijual' => true,
        'butuh_proses' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'jenis_barang_id',
                'nomer_seri',
                'barcode',
                'nama_barang',
                'gambar',
                'tipe_barang',
                'status',
                'butuh_proses',
                'bisa_dijual',
                'harga_jual',
                'satuan',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'butuh_proses' => 'boolean',
            'bisa_dijual' => 'boolean',
        ];
    }

    public function getGambarUrlAttribute(): ?string
    {
        if (empty($this->gambar)) {
            return null;
        }

        if (str_starts_with($this->gambar, 'http://') || str_starts_with($this->gambar, 'https://')) {
            return $this->gambar;
        }

        return asset('storage/' . $this->gambar);
    }

    public function scopeBisaDijual($query)
    {
        return $query->where('bisa_dijual', true);
    }

    protected static function booted(): void
    {
        static::creating(function (Barang $barang) {
            if (empty($barang->nomer_seri) && !empty($barang->jenis_barang_id)) {
                $jenis = JenisBarang::find($barang->jenis_barang_id);
                if ($jenis) {
                    $barang->nomer_seri = $jenis->generateNextNomerSeri();
                }
            }

            if (empty($barang->barcode)) {
                $barang->barcode = $barang->nomer_seri;
            }
        });

        static::updating(function (Barang $barang) {
            if (empty($barang->nomer_seri) && !empty($barang->jenis_barang_id)) {
                $jenis = JenisBarang::find($barang->jenis_barang_id);
                if ($jenis) {
                    $barang->nomer_seri = $jenis->generateNextNomerSeri();
                }
            }

            if (empty($barang->barcode)) {
                $barang->barcode = $barang->nomer_seri;
            }
        });
    }

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'barang_gudang')
            ->withPivot('stok')
            ->withTimestamps();
    }

    /**
     * Hitung faktor konversi satuan terhadap Level 1 (Satuan Utama / Pertama).
     */
    public function getFaktorKonversi(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return 1;
        }

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return max(1, (int) ($this->isi_satuan_2 ?? 1));
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return max(1, (int) ($this->isi_satuan_3 ?? 1));
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return max(1, (int) ($this->isi_satuan_4 ?? 1));
        }

        return 1;
    }

    /**
     * Dapatkan harga jual per-satuan (khusus/grosir jika terisi, atau perkalian dari Level 1).
     */
    public function getHargaJualForSatuan(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return (int) $this->harga_jual;
        }

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return filled($this->harga_jual_2) ? (int) $this->harga_jual_2 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_2));
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return filled($this->harga_jual_3) ? (int) $this->harga_jual_3 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_3));
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return filled($this->harga_jual_4) ? (int) $this->harga_jual_4 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_4));
        }

        return (int) $this->harga_jual;
    }

    /**
     * Hitung harga per-unit berdasarkan tier quantity bertingkat.
     */
    public function getHargaTierForQty(int $qty, ?string $satuan = null): int
    {
        $basePrice = $this->getHargaJualForSatuan($satuan);
        $tipe = $this->tipe_harga_bertingkat ?? 'persen';
        $faktor = $this->getFaktorKonversi($satuan);
        $totalQtyDasar = $qty * $faktor;

        $tiers = [];
        if (filled($this->min_qty_3) && (int)$this->min_qty_3 > 0 && (float)($this->nilai_tier_3 ?? 0) > 0) {
            $tiers[] = ['min_qty' => (int) $this->min_qty_3, 'nilai' => (float) $this->nilai_tier_3];
        }
        if (filled($this->min_qty_2) && (int)$this->min_qty_2 > 0 && (float)($this->nilai_tier_2 ?? 0) > 0) {
            $tiers[] = ['min_qty' => (int) $this->min_qty_2, 'nilai' => (float) $this->nilai_tier_2];
        }
        if (filled($this->min_qty_1) && (int)$this->min_qty_1 > 0 && (float)($this->nilai_tier_1 ?? 0) > 0) {
            $tiers[] = ['min_qty' => (int) $this->min_qty_1, 'nilai' => (float) $this->nilai_tier_1];
        }

        usort($tiers, fn($a, $b) => $b['min_qty'] <=> $a['min_qty']);

        $matchedNilai = null;
        foreach ($tiers as $t) {
            if ($totalQtyDasar >= $t['min_qty']) {
                $matchedNilai = $t['nilai'];
                break;
            }
        }

        if ($matchedNilai === null || $matchedNilai <= 0) {
            return $basePrice;
        }

        if ($tipe === 'persen') {
            $discounted = $basePrice * (1 - ($matchedNilai / 100));
            return max(0, (int) round($discounted));
        }

        if ($tipe === 'nominal') {
            return max(0, (int) round($matchedNilai * $faktor));
        }

        return $basePrice;
    }

    /**
     * Dapatkan harga beli per-satuan (khusus jika terisi, atau perkalian dari Level 1).
     */
    public function getHargaBeliForSatuan(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return (int) $this->harga_beli;
        }

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return filled($this->harga_beli_2) ? (int) $this->harga_beli_2 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_2));
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return filled($this->harga_beli_3) ? (int) $this->harga_beli_3 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_3));
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return filled($this->harga_beli_4) ? (int) $this->harga_beli_4 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_4));
        }

        return (int) $this->harga_beli;
    }

    /**
     * Dapatkan HPP Average per-satuan (khusus jika terisi, atau perkalian dari Level 1).
     */
    public function getHppForSatuan(?string $namaSatuan): int
    {
        $baseHpp = (float) ($this->hpp ?? $this->harga_beli);

        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return (int) round($baseHpp);
        }

        $faktor = $this->getFaktorKonversi($namaSatuan);

        return (int) round($baseHpp * $faktor);
    }

    /**
     * Daftar seluruh level satuan aktif yang dimiliki barang ini.
     */
    public function getAvailableUnits(): array
    {
        $units = [];

        $baseSatuan = $this->satuan ?? 'Pcs';

        $units[] = [
            'level' => 1,
            'satuan' => $baseSatuan,
            'faktor' => 1,
            'isi_info' => null,
            'harga_jual' => (int) $this->harga_jual,
            'harga_beli' => (int) $this->harga_beli,
            'hpp' => $this->getHppForSatuan($baseSatuan),
        ];

        if (!empty($this->satuan_2)) {
            $isi2 = max(1, (int) ($this->isi_satuan_2 ?? 1));
            $units[] = [
                'level' => 2,
                'satuan' => $this->satuan_2,
                'faktor' => $this->getFaktorKonversi($this->satuan_2),
                'isi_info' => "1 {$this->satuan_2} = {$isi2} {$baseSatuan}",
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_2),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_2),
                'hpp' => $this->getHppForSatuan($this->satuan_2),
            ];
        }

        if (!empty($this->satuan_3)) {
            $isi3 = max(1, (int) ($this->isi_satuan_3 ?? 1));
            $faktor3 = $this->getFaktorKonversi($this->satuan_3);
            $isiStr = !empty($this->satuan_2)
                ? "1 {$this->satuan_3} = {$isi3} {$this->satuan_2} ({$faktor3} {$baseSatuan})"
                : "1 {$this->satuan_3} = {$faktor3} {$baseSatuan}";

            $units[] = [
                'level' => 3,
                'satuan' => $this->satuan_3,
                'faktor' => $faktor3,
                'isi_info' => $isiStr,
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_3),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_3),
                'hpp' => $this->getHppForSatuan($this->satuan_3),
            ];
        }

        if (!empty($this->satuan_4)) {
            $isi4 = max(1, (int) ($this->isi_satuan_4 ?? 1));
            $faktor4 = $this->getFaktorKonversi($this->satuan_4);
            $prevSat = $this->satuan_3 ?? $this->satuan_2 ?? $baseSatuan;
            $isiStr = "1 {$this->satuan_4} = {$isi4} {$prevSat} ({$faktor4} {$baseSatuan})";

            $units[] = [
                'level' => 4,
                'satuan' => $this->satuan_4,
                'faktor' => $faktor4,
                'isi_info' => $isiStr,
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_4),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_4),
                'hpp' => $this->getHppForSatuan($this->satuan_4),
            ];
        }

        return $units;
    }

    /**
     * Format jumlah stok dasar (misal Pcs) menjadi rincian satuan berantai.
     * Contoh: 1255 pcs -> "1 Bal, 2 Slof, 5 Pack, 5 Pcs"
     */
    public function formatStokBerantai(int $stok): string
    {
        $baseSatuan = $this->satuan ?? 'Pcs';
        if ($stok <= 0) {
            return "0 {$baseSatuan}";
        }

        $units = $this->getAvailableUnits();
        usort($units, fn($a, $b) => $b['faktor'] <=> $a['faktor']);

        $sisa = $stok;
        $parts = [];

        foreach ($units as $u) {
            $faktor = $u['faktor'];
            if ($faktor > 1 && $sisa >= $faktor) {
                $qty = (int) floor($sisa / $faktor);
                $sisa %= $faktor;
                $parts[] = "{$qty} {$u['satuan']}";
            } elseif ($faktor === 1 && ($sisa > 0 || empty($parts))) {
                $parts[] = "{$sisa} {$u['satuan']}";
                $sisa = 0;
            }
        }

        return empty($parts) ? "0 {$baseSatuan}" : implode(', ', $parts);
    }

    public function historiHpps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HistoriHpp::class, 'barang_id')->orderBy('tanggal', 'desc');
    }
}
