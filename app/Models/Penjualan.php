<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Penjualan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'penjualan';

    protected $fillable = [
        'nomer_nota',
        'karyawan_id',
        'user_id',
        'gudang_id',
        'tanggal',
        'total',
        'diskon',
        'neto',
        'jenis_pembayaran',
        'bayar',
        'kembalian',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id_karyawan');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNamaKasirAttribute(): string
    {
        if ($this->karyawan) {
            return $this->karyawan->nama_karyawan;
        }

        if ($this->user) {
            return $this->user->name . ' [Admin]';
        }

        return '-';
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function details()
    {
        return $this->hasMany(DetailJual::class, 'penjualan_id');
    }
}
