<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            // meja nullable: take away / delivery tidak punya meja
            $table->foreignId('meja_id')->nullable()->after('aplikator_id')
                ->constrained('meja')->nullOnDelete();
            // total menit meja dipegang, dari sesi_meja.mulai sampai pembayaran
            $table->unsignedInteger('durasi_meja_menit')->nullable()->after('meja_id');
        });

        Schema::table('order_pending', function (Blueprint $table) {
            // meja ikut terpegang selama order pending (pelanggan sudah duduk)
            $table->foreignId('meja_id')->nullable()->after('aplikator_id')
                ->constrained('meja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_pending', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meja_id');
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meja_id');
            $table->dropColumn('durasi_meja_menit');
        });
    }
};
