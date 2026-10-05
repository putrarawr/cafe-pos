<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $kode_order
 * @property int $gudang_id
 * @property string $status
 * @property int|null $karyawan_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $tanggal
 * @property int $diskon_persen
 * @property int $total
 * @property int $diskon
 * @property int $neto
 * @property int $biaya_kirim
 * @property string|null $alamat_pengiriman
 * @property int|null $aplikator_id
 * @property string|null $catatan
 * @property int|null $penjualan_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrderPending extends Model
{
    use LogsActivity;

    protected $table = 'order_pending';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_BATAL = 'batal';

    protected $fillable = [
        'kode_order',
        'gudang_id',
        'status',
        'karyawan_id',
        'user_id',
        'tanggal',
        'diskon_persen',
        'total',
        'diskon',
        'neto',
        'biaya_kirim',
        'alamat_pengiriman',
        'aplikator_id',
        'catatan',
        'penjualan_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'diskon_persen' => 'integer',
            'total' => 'integer',
            'diskon' => 'integer',
            'neto' => 'integer',
            'biaya_kirim' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function items()
    {
        return $this->hasMany(OrderPendingItem::class, 'order_pending_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id_karyawan');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aplikator()
    {
        return $this->belongsTo(Aplikator::class, 'aplikator_id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
