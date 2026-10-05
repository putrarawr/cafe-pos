<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangGudang extends Model
{
    protected $table = 'barang_gudang';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'stok_minimum' => 'integer',
            'stok_maksimum' => 'integer',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    /**
     * Hitung selisih defisit terhadap stok minimum gudang.
     */
    public function getDefisitAttribute(): int
    {
        $min = (int) ($this->stok_minimum ?? 0);
        $current = (int) ($this->stok ?? 0);

        return $current < $min ? ($min - $current) : 0;
    }

    /**
     * Cek apakah stok di gudang ini melebihi kapasitas maksimum.
     */
    public function getIsOverstockAttribute(): bool
    {
        $max = (int) ($this->stok_maksimum ?? 0);

        return $max > 0 && (int) $this->stok > $max;
    }

    /**
     * Hitung jumlah unit yang melebihi batas maksimum.
     */
    public function getKelebihanStokAttribute(): int
    {
        $max = (int) ($this->stok_maksimum ?? 0);
        $current = (int) ($this->stok ?? 0);

        return ($max > 0 && $current > $max) ? ($current - $max) : 0;
    }

    /**
     * Tentukan status stok untuk gudang ini.
     * Mengembalikan: 'habis', 'menipis', 'overstock', atau 'aman'
     */
    public function getStatusStokAttribute(): string
    {
        $current = (int) ($this->stok ?? 0);
        $min = (int) ($this->stok_minimum ?? 0);
        $max = (int) ($this->stok_maksimum ?? 0);

        if ($current <= 0) {
            return 'habis';
        }

        if ($min > 0 && $current <= $min) {
            return 'menipis';
        }

        if ($max > 0 && $current > $max) {
            return 'overstock';
        }

        return 'aman';
    }
}
