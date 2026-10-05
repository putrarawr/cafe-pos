<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                UPDATE barang
                SET stok_minimum = COALESCE(sub.total_min, 0)
                FROM (
                    SELECT barang_id, SUM(stok_minimum) AS total_min
                    FROM barang_gudang
                    GROUP BY barang_id
                ) sub
                WHERE barang.id = sub.barang_id
                  AND barang.tipe_barang != 'barang_jadi'
                  AND barang.stok_minimum > 0
            ");
        } else {
            $items = DB::table('barang')
                ->where('tipe_barang', '!=', 'barang_jadi')
                ->where('stok_minimum', '>', 0)
                ->select('id')
                ->get();

            foreach ($items as $item) {
                $total = (int) DB::table('barang_gudang')
                    ->where('barang_id', $item->id)
                    ->sum('stok_minimum');

                DB::table('barang')
                    ->where('id', $item->id)
                    ->update(['stok_minimum' => $total]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data sync migration, no schema changes to revert.
    }
};
