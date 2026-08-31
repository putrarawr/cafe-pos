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
        Schema::table('jenis_barang', function (Blueprint $table) {
            $table->string('kode_jenis')->nullable()->after('nama_jenis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jenis_barang', function (Blueprint $table) {
            $table->dropColumn('kode_jenis');
        });
    }
};
