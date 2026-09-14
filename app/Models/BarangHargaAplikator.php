<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangHargaAplikator extends Model
{
    use HasFactory;

    protected $table = 'barang_harga_aplikator';

    protected $fillable = [
        'barang_id',
        'aplikator_id',
        'harga_jual',
    ];

    protected $casts = [
        'harga_jual' => 'integer',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function aplikator(): BelongsTo
    {
        return $this->belongsTo(Aplikator::class, 'aplikator_id');
    }
}
