<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Supplier extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'supplier';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nama_supplier',
        'no_telepon',
        'alamat',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
