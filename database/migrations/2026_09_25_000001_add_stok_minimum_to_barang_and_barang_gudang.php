<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->integer('stok_minimum')->default(20)->after('status');
        });

        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->integer('stok_minimum')->default(20)->after('stok');
        });

        // Set stok_minimum = 0 untuk barang_jadi (menu olahan) eksisting
        DB::table('barang')
            ->where('tipe_barang', 'barang_jadi')
            ->update(['stok_minimum' => 0]);

        // Sinkronkan stok_minimum pada barang_gudang eksisting mengikuti master barang
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE barang_gudang
                SET stok_minimum = barang.stok_minimum
                FROM barang
                WHERE barang_gudang.barang_id = barang.id
            ');
        } else {
            $items = DB::table('barang')->select('id', 'stok_minimum')->get();
            foreach ($items as $item) {
                DB::table('barang_gudang')
                    ->where('barang_id', $item->id)
                    ->update(['stok_minimum' => $item->stok_minimum]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->dropColumn('stok_minimum');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('stok_minimum');
        });
    }
};
