<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailJual extends Model
{
    use HasFactory;

    protected $table = 'detail_jual';

    protected $fillable = [
        'penjualan_id',
        'barang_id',
        'gudang_id',
        'satuan',
        'jumlah',
        'harga',
        'hpp',
        'diskon',
        'subtotal',
        'is_bonus',
        'promo_id',
        'bonus_barang_id',
        'bonus_qty',
        'bonus_satuan',
        'bonus_hpp',
        'jenis_pesanan',
    ];

    protected $casts = [
        'is_bonus' => 'boolean',
        'promo_id' => 'integer',
        'bonus_barang_id' => 'integer',
        'bonus_qty' => 'integer',
        'bonus_hpp' => 'float',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function bonusBarang()
    {
        return $this->belongsTo(Barang::class, 'bonus_barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function promoBonus()
    {
        return $this->belongsTo(PromoBonus::class, 'promo_id');
    }

    /**
     * Hitung laba bersih: subtotal - (HPP barang utama + HPP bonus).
     */
    public function getLabaBersihAttribute(): float
    {
        $hppUtama = ((float) $this->jumlah) * ((float) ($this->hpp ?? 0));
        $hppBonus = ((float) ($this->bonus_qty ?? 0)) * ((float) ($this->bonus_hpp ?? 0));

        return (float) ($this->subtotal ?? 0) - ($hppUtama + $hppBonus);
    }
}
