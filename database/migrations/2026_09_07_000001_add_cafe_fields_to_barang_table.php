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
            if (!Schema::hasColumn('barang', 'gambar')) {
                $table->string('gambar')->nullable()->after('nama_barang');
            }
            if (!Schema::hasColumn('barang', 'tipe_barang')) {
                $table->string('tipe_barang')->default('barang_dagang')->after('jenis_barang_id');
            }
            if (!Schema::hasColumn('barang', 'status')) {
                $table->string('status')->default('tersedia')->after('satuan');
            }
            if (!Schema::hasColumn('barang', 'butuh_proses')) {
                $table->boolean('butuh_proses')->default(false)->after('status');
            }
            if (!Schema::hasColumn('barang', 'bisa_dijual')) {
                $table->boolean('bisa_dijual')->default(true)->after('butuh_proses');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['tipe_barang', 'status', 'butuh_proses', 'bisa_dijual'] as $col) {
                if (Schema::hasColumn('barang', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
