<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Gudang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'gudang';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_gudang', 'alamat'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function barangs()
    {
        return $this->belongsToMany(Barang::class, 'barang_gudang')
            ->withPivot('stok')
            ->withTimestamps();
    }
}
