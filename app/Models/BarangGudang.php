<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangGudang extends Model
{
    protected $table = 'barang_gudang';

    protected $guarded = ['id'];

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
     * Tentukan status stok untuk gudang ini.
     * Mengembalikan: 'habis', 'menipis', atau 'aman'
     */
    public function getStatusStokAttribute(): string
    {
        $current = (int) ($this->stok ?? 0);
        $min = (int) ($this->stok_minimum ?? 0);

        if ($current <= 0) {
            return 'habis';
        }

        if ($min > 0 && $current <= $min) {
            return 'menipis';
        }

        return 'aman';
    }
}
