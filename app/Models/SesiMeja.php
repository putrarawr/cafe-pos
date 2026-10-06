<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu periode pemakaian satu meja.
 *
 * Hanya status TERISI yang mengunci meja. Sesi ditutup (selesai/batal) saat
 * order dibayar atau order pending dibatalkan, dan setelah itu meja langsung
 * bisa dipilih lagi.
 */
class SesiMeja extends Model
{
    protected $table = 'sesi_meja';

    public const STATUS_TERISI = 'terisi';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_BATAL = 'batal';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'batas_menit' => 'integer',
            'durasi_menit_terpakai' => 'integer',
            'overstay' => 'boolean',
        ];
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::STATUS_TERISI);
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class, 'meja_id');
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

    public function orderPending()
    {
        return $this->belongsTo(OrderPending::class, 'order_pending_id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function isTerisi(): bool
    {
        return $this->status === self::STATUS_TERISI;
    }

    /**
     * Lama pemakaian dalam menit. Sesi yang masih berjalan dihitung sampai sekarang.
     */
    public function lamaMenit(): int
    {
        if (! $this->mulai) {
            return 0;
        }

        $selesai = $this->selesai instanceof Carbon ? $this->selesai : now();

        return (int) floor(abs($selesai->getTimestamp() - $this->mulai->getTimestamp()) / 60);
    }

    public function sisaMenit(): int
    {
        return max(0, (int) $this->batas_menit - $this->lamaMenit());
    }

    /**
     * Meja yang masih terisi tapi sudah melewati batas durasi (overstay).
     */
    public function sudahOver(): bool
    {
        return $this->isTerisi() && $this->sisaMenit() === 0;
    }

    /**
     * Tandai sesi selesai: dipakai server saat bayar / batal / dilepas manual.
     */
    public function tutup(string $status, ?int $penjualanId = null): self
    {
        $this->status = $status;
        $this->selesai = now();
        $this->durasi_menit_terpakai = $this->lamaMenit();
        $this->overstay = $this->sisaMenit() === 0 && $status === self::STATUS_SELESAI;

        if ($penjualanId !== null) {
            $this->penjualan_id = $penjualanId;
        }

        return $this;
    }
}
