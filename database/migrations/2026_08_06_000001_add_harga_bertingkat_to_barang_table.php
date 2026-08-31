<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            if (!Schema::hasColumn('barang', 'tipe_harga_bertingkat')) {
                $table->enum('tipe_harga_bertingkat', ['persen', 'nominal'])->default('persen')->after('harga_jual');
            }
            if (!Schema::hasColumn('barang', 'min_qty_1')) {
                $table->integer('min_qty_1')->nullable()->default(1);
            }
            if (!Schema::hasColumn('barang', 'nilai_tier_1')) {
                $table->decimal('nilai_tier_1', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('barang', 'min_qty_2')) {
                $table->integer('min_qty_2')->nullable();
            }
            if (!Schema::hasColumn('barang', 'nilai_tier_2')) {
                $table->decimal('nilai_tier_2', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('barang', 'min_qty_3')) {
                $table->integer('min_qty_3')->nullable();
            }
            if (!Schema::hasColumn('barang', 'nilai_tier_3')) {
                $table->decimal('nilai_tier_3', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $cols = [
                'tipe_harga_bertingkat',
                'min_qty_1', 'nilai_tier_1',
                'min_qty_2', 'nilai_tier_2',
                'min_qty_3', 'nilai_tier_3',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('barang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
