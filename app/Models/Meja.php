<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Meja extends Model
{
    use LogsActivity;

    protected $table = 'meja';

    protected $guarded = ['id'];

    protected $casts = [
        'kapasitas' => 'integer',
        'durasi_menit' => 'integer',
        'status_aktif' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['kode_meja', 'nama_meja', 'gudang_id', 'area', 'kapasitas', 'durasi_menit', 'status_aktif'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function sesi()
    {
        return $this->hasMany(SesiMeja::class, 'meja_id');
    }

    /**
     * Sesi yang sedang mengunci meja ini (pelanggan masih di sana).
     */
    public function sesiAktif()
    {
        return $this->hasOne(SesiMeja::class, 'meja_id')
            ->where('status', SesiMeja::STATUS_TERISI);
    }
}
