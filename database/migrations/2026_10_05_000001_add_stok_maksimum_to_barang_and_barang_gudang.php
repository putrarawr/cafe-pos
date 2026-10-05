<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->integer('stok_maksimum')->default(0)->after('stok_minimum');
        });

        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->integer('stok_maksimum')->default(0)->after('stok_minimum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang_gudang', function (Blueprint $table) {
            $table->dropColumn('stok_maksimum');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('stok_maksimum');
        });
    }
};
