<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriHpp extends Model
{
    use HasFactory;

    protected $table = 'histori_hpp';

    protected $fillable = [
        'barang_id',
        'pembelian_id',
        'supplier_id',
        'nomer_entry',
        'tanggal',
        'stok_sebelum',
        'stok_sesudah',
        'qty_beli',
        'satuan',
        'harga_beli_masuk',
        'hpp_sebelum',
        'hpp_sesudah',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'stok_sebelum' => 'integer',
        'stok_sesudah' => 'integer',
        'qty_beli' => 'integer',
        'harga_beli_masuk' => 'float',
        'hpp_sebelum' => 'float',
        'hpp_sesudah' => 'float',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'pembelian_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
