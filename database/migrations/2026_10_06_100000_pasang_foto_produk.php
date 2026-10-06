<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pasang kembali foto produk yang hilang.
 *
 * Foto sudah ada di storage/app/public/barang sejak 7 Sep 2026, tapi kolom
 * `gambar` kosong semua karena data barang di-reseed ulang. Pemetaan diambil
 * dari database/seeders/data/foto_produk.php supaya sama dengan MasterDataSeeder
 * dan tidak perlu diduplikasi di dua tempat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $foto = require database_path('seeders/data/foto_produk.php');

        foreach ($foto as $nama => $path) {
            $terpasang = DB::table('barang')
                ->where('nama_barang', $nama)
                ->update(['gambar' => $path]);

            if ($terpasang === 0) {
                echo "  barang \"{$nama}\" tidak ada, foto dilewati\n";
            }
        }
    }

    public function down(): void
    {
        DB::table('barang')
            ->whereIn('gambar', array_values(require database_path('seeders/data/foto_produk.php')))
            ->update(['gambar' => null]);
    }
};