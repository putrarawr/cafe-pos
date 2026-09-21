<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Aplikator extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'aplikator';

    protected $fillable = [
        'nama_aplikator',
        'kode_aplikator',
        'persentase_komisi',
        'gambar',
        'keterangan',
        'status_aktif',
    ];

    protected $casts = [
        'persentase_komisi' => 'float',
        'status_aktif' => 'boolean',
    ];

    public function getGambarUrlAttribute(): ?string
    {
        if (empty($this->gambar)) {
            return null;
        }

        if (str_starts_with($this->gambar, 'http://') || str_starts_with($this->gambar, 'https://')) {
            return $this->gambar;
        }

        return asset('storage/' . $this->gambar);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function barangHargaAplikators(): HasMany
    {
        return $this->hasMany(BarangHargaAplikator::class, 'aplikator_id');
    }

    public function barangs(): BelongsToMany
    {
        return $this->belongsToMany(Barang::class, 'barang_harga_aplikator')
            ->withPivot('harga_jual')
            ->withTimestamps();
    }
}
