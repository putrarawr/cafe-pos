<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Karyawan extends Authenticatable
{
    use HasFactory, LogsActivity, Notifiable;

    protected $table = 'karyawan';

    protected $primaryKey = 'id_karyawan';

    protected $fillable = [
        'nama_karyawan',
        'email',
        'password',
        'no_telp',
        'alamat',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
