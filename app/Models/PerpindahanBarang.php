<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class PerpindahanBarang extends Model
{
    use LogsActivity;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->nomer_entry)) {
                $prefix = 'MV-' . date('Ymd') . '-';
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
            ->logUnguarded()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
    protected $table = 'perpindahan_barang';
    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(PerpindahanBarangDetail::class, 'perpindahan_barang_id');
    }

    public function gudangAsal()
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan()
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
