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
        Schema::table('histori_hpp', function (Blueprint $table) {
            $table->integer('stok_sesudah')->default(0)->after('stok_sebelum');
        });

        // Backfill stok_sesudah = stok_sebelum + qty_beli
        DB::statement('
            UPDATE histori_hpp
            SET stok_sesudah = stok_sebelum + qty_beli
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('histori_hpp', function (Blueprint $table) {
            $table->dropColumn('stok_sesudah');
        });
    }
};
