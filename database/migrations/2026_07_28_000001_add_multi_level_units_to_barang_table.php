<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            if (!Schema::hasColumn('barang', 'satuan')) {
                $table->string('satuan')->default('Pcs')->after('nama_barang');
            }

            // Level 2
            if (!Schema::hasColumn('barang', 'satuan_2')) {
                $table->string('satuan_2')->nullable();
            }
            if (!Schema::hasColumn('barang', 'isi_satuan_2')) {
                $table->integer('isi_satuan_2')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_beli_2')) {
                $table->integer('harga_beli_2')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_jual_2')) {
                $table->integer('harga_jual_2')->nullable();
            }

            // Level 3
            if (!Schema::hasColumn('barang', 'satuan_3')) {
                $table->string('satuan_3')->nullable();
            }
            if (!Schema::hasColumn('barang', 'isi_satuan_3')) {
                $table->integer('isi_satuan_3')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_beli_3')) {
                $table->integer('harga_beli_3')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_jual_3')) {
                $table->integer('harga_jual_3')->nullable();
            }

            // Level 4
            if (!Schema::hasColumn('barang', 'satuan_4')) {
                $table->string('satuan_4')->nullable();
            }
            if (!Schema::hasColumn('barang', 'isi_satuan_4')) {
                $table->integer('isi_satuan_4')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_beli_4')) {
                $table->integer('harga_beli_4')->nullable();
            }
            if (!Schema::hasColumn('barang', 'harga_jual_4')) {
                $table->integer('harga_jual_4')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $cols = [
                'satuan_2', 'isi_satuan_2', 'harga_beli_2', 'harga_jual_2',
                'satuan_3', 'isi_satuan_3', 'harga_beli_3', 'harga_jual_3',
                'satuan_4', 'isi_satuan_4', 'harga_beli_4', 'harga_jual_4',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('barang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
