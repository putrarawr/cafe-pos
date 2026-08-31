<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class PerpindahanBarangDetail extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
    protected $table = 'perpindahan_barang_detail';
    protected $guarded = ['id'];

    public function perpindahanBarang()
    {
        return $this->belongsTo(PerpindahanBarang::class, 'perpindahan_barang_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
