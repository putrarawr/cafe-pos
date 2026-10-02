<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPendingItem extends Model
{
    protected $table = 'order_pending_item';

    protected $fillable = [
        'order_pending_id',
        'barang_id',
        'gudang_id',
        'satuan',
        'jumlah',
        'jumlah_dasar',
        'harga',
        'diskon',
        'subtotal',
        'is_bonus',
        'promo_id',
        'jenis_pesanan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'jumlah_dasar' => 'integer',
            'harga' => 'integer',
            'diskon' => 'integer',
            'subtotal' => 'integer',
            'is_bonus' => 'boolean',
        ];
    }

    public function orderPending()
    {
        return $this->belongsTo(OrderPending::class, 'order_pending_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
