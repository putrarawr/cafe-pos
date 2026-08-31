<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index komposit untuk lookup stok cepat (used by StokService & kasir)
        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->index(['barang_id', 'gudang_id'], 'barang_gudang_lookup_idx');
        });

        // Index untuk pencarian penjualan per tanggal (nota number generation)
        Schema::table('penjualan', function (Blueprint $table) {
            $table->index('tanggal', 'penjualan_tanggal_idx');
        });

        // Index untuk promo bonus active scope
        Schema::table('promo_bonus', function (Blueprint $table) {
            $table->index(['is_aktif', 'tanggal_mulai', 'tanggal_selesai'], 'promo_bonus_active_idx');
        });

        // Index untuk detail_jual lookup per penjualan
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->index('penjualan_id', 'detail_jual_penjualan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->dropIndex('barang_gudang_lookup_idx');
        });
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropIndex('penjualan_tanggal_idx');
        });
        Schema::table('promo_bonus', function (Blueprint $table) {
            $table->dropIndex('promo_bonus_active_idx');
        });
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropIndex('detail_jual_penjualan_idx');
        });
    }
};
