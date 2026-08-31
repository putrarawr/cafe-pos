<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class JenisBarang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'jenis_barang';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_jenis', 'kode_jenis', 'deskripsi'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Dapatkan kode prefix (misal: RKK / ROK).
     * Jika kode_jenis kosong, otomatis ambil 3 huruf kapital pertama dari nama_jenis.
     */
    public function getEffectiveKodePrefix(): string
    {
        if (filled($this->kode_jenis)) {
            return strtoupper(trim($this->kode_jenis));
        }

        $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $this->nama_jenis ?? 'BRG'));
        return substr($clean, 0, 3) ?: 'BRG';
    }

    /**
     * Generate Nomor Seri berikutnya untuk barang di bawah jenis barang ini.
     * Contoh: ROK-0001, ROK-0002, dst.
     */
    public function generateNextNomerSeri(): string
    {
        $prefix = $this->getEffectiveKodePrefix();
        $driver = \DB::connection()->getDriverName();

        $query = Barang::where('nomer_seri', 'LIKE', "{$prefix}-%");

        if ($driver === 'pgsql') {
            $lastBarang = $query
                ->orderByRaw("NULLIF(regexp_replace(nomer_seri, '^.*-', ''), '')::INTEGER DESC")
                ->first();
        } elseif ($driver === 'sqlite') {
            $lastBarang = $query
                ->orderByRaw("CAST(substr(nomer_seri, instr(nomer_seri, '-') + 1) AS INTEGER) DESC")
                ->first();
        } else {
            $lastBarang = $query
                ->orderByRaw("CAST(SUBSTRING_INDEX(nomer_seri, '-', -1) AS UNSIGNED) DESC")
                ->first();
        }

        $nextNumber = 1;
        if ($lastBarang && !empty($lastBarang->nomer_seri)) {
            $parts = explode('-', $lastBarang->nomer_seri);
            $lastSeq = (int) end($parts);
            if ($lastSeq > 0) {
                $nextNumber = $lastSeq + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $nextNumber);
    }

    public function barangs()
    {
        return $this->hasMany(
            Barang::class,
            'jenis_barang_id'
        );
    }
}
