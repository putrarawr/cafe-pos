<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoBonus extends Model
{
    use HasFactory;

    protected $table = 'promo_bonus';

    protected $fillable = [
        'nama_promo',
        'barang_utama_id',
        'min_qty_utama',
        'satuan_utama',
        'barang_bonus_id',
        'qty_bonus',
        'satuan_bonus',
        'is_kelipatan',
        'is_aktif',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $casts = [
        'min_qty_utama' => 'integer',
        'qty_bonus' => 'integer',
        'is_kelipatan' => 'boolean',
        'is_aktif' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function barangUtama()
    {
        return $this->belongsTo(Barang::class, 'barang_utama_id');
    }

    public function barangBonus()
    {
        return $this->belongsTo(Barang::class, 'barang_bonus_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->format('Y-m-d');

        return $query->where('is_aktif', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_mulai')
                    ->orWhere('tanggal_mulai', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $today);
            });
    }
}
