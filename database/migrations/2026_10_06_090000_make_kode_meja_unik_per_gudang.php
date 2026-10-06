<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penamaan meja disederhanakan jadi "Meja 01", "Meja 02", ... dan diulang per
 * cabang. Karena kode yang sama kini boleh dipakai di cabang berbeda, unique
 * global dilepas dan diganti jadi unik per (gudang_id, kode_meja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meja', function (Blueprint $table) {
            $table->dropUnique(['kode_meja']);
        });

        DB::table('meja')
            ->orderBy('gudang_id')
            ->orderBy('id')
            ->get()
            ->groupBy('gudang_id')
            ->each(function ($mejas) {
                foreach ($mejas->values() as $nomor => $meja) {
                    DB::table('meja')
                        ->where('id', $meja->id)
                        ->update([
                            'kode_meja' => $this->kode($nomor + 1),
                            'nama_meja' => $meja->area ?: $meja->nama_meja,
                        ]);
                }
            });

        Schema::table('meja', function (Blueprint $table) {
            $table->unique(['gudang_id', 'kode_meja'], 'meja_gudang_kode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meja', function (Blueprint $table) {
            $table->dropUnique('meja_gudang_kode_unique');
        });

        // kembalikan pola kode lama per cabang supaya unique global bisa dibuat lagi.
        $noGudang = 0;
        DB::table('meja')
            ->orderBy('gudang_id')
            ->orderBy('id')
            ->get()
            ->groupBy('gudang_id')
            ->each(function ($mejas) use (&$noGudang) {
                $noGudang++;
                foreach ($mejas->values() as $nomor => $meja) {
                    DB::table('meja')
                        ->where('id', $meja->id)
                        ->update([
                            'kode_meja' => 'G'.$noGudang.'-M'.str_pad((string) ($nomor + 1), 2, '0', STR_PAD_LEFT),
                        ]);
                }
            });

        Schema::table('meja', function (Blueprint $table) {
            $table->unique('kode_meja');
        });
    }

    private function kode(int $nomor): string
    {
        return 'Meja '.str_pad((string) $nomor, 2, '0', STR_PAD_LEFT);
    }
};