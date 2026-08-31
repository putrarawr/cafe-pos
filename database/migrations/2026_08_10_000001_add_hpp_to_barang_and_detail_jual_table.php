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
            $table->decimal('hpp', 15, 2)->default(0)->after('harga_jual');
        });

        // Inisialisasi nilai hpp awal disamakan dengan harga_beli yang ada saat ini
        DB::statement('UPDATE barang SET hpp = harga_beli WHERE hpp = 0 OR hpp IS NULL');

        Schema::table('detail_jual', function (Blueprint $table) {
            $table->decimal('hpp', 15, 2)->default(0)->after('harga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('hpp');
        });

        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropColumn('hpp');
        });
    }
};
