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
        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->string('nomer_seri')->nullable()->after('barang_id')->index();
        });

        // Backfill nomer_seri from barang table
        DB::statement('
            UPDATE kartu_stok
            SET nomer_seri = barang.nomer_seri
            FROM barang
            WHERE kartu_stok.barang_id = barang.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->dropColumn('nomer_seri');
        });
    }
};
