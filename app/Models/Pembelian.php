<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Pembelian extends Model
{
    use HasFactory, LogsActivity;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->nomer_entry)) {
                $prefix = 'PB-' . date('Ymd') . '-';
                $latest = self::where('nomer_entry', 'like', $prefix . '%')
                    ->orderBy('nomer_entry', 'desc')
                    ->lockForUpdate()
                    ->first();

                if ($latest) {
                    $sequence = (int) substr($latest->nomer_entry, -4);
                    $nextSequence = str_pad($sequence + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $nextSequence = '0001';
                }

                $model->nomer_entry = $prefix . $nextSequence;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'pembelian';

    protected $fillable = [
        'nomer_entry',
        'supplier_id',
        'gudang_id',
        'user_id',
        'tanggal',
        'total',
        'diskon',
        'neto',
        'jenis_pembayaran',
        'keterangan',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(DetailBeli::class, 'pembelian_id');
    }
}
