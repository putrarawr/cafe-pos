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
        $barangs = DB::table('barang')->select('id', 'stok_minimum')->get();
        $gudangs = DB::table('gudang')->select('id')->get();

        if ($barangs->isEmpty() || $gudangs->isEmpty()) {
            return;
        }

        $now = now();
        $existing = DB::table('barang_gudang')
            ->select('barang_id', 'gudang_id')
            ->get()
            ->map(fn ($r) => "{$r->barang_id}_{$r->gudang_id}")
            ->flip();

        $rowsToInsert = [];
        foreach ($barangs as $barang) {
            foreach ($gudangs as $gudang) {
                $key = "{$barang->id}_{$gudang->id}";
                if (! isset($existing[$key])) {
                    $rowsToInsert[] = [
                        'barang_id' => $barang->id,
                        'gudang_id' => $gudang->id,
                        'stok' => 0,
                        'stok_minimum' => (int) ($barang->stok_minimum ?? 20),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($rowsToInsert)) {
            foreach (array_chunk($rowsToInsert, 500) as $chunk) {
                DB::table('barang_gudang')->insert($chunk);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data sync migration; no destructive reverse required.
    }
};
