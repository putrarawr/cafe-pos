<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuStok extends Model
{
    use HasFactory;

    protected $table = 'kartu_stok';

    public const JENIS_MASUK = 'masuk';
    public const JENIS_KELUAR = 'keluar';
    public const JENIS_PINDAH_MASUK = 'pindah_masuk';
    public const JENIS_PINDAH_KELUAR = 'pindah_keluar';
    public const JENIS_KOREKSI = 'koreksi';

    protected $fillable = [
        'barang_id',
        'nomer_seri',
        'gudang_id',
        'nomer_entry',
        'tanggal',
        'keterangan',
        'jenis_transaksi',
        'jumlah',
        'harga',
        'saldo',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
