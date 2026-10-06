<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master meja untuk pesanan dine in. Discope per gudang supaya halaman
        // kasir (yang sudah punya pemilih gudang) tidak bisa memilih meja cabang lain.
        Schema::create('meja', function (Blueprint $table) {
            $table->id();
            $table->string('kode_meja', 50)->unique();
            $table->string('nama_meja', 100);
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('area')->nullable();
            $table->unsignedSmallInteger('kapasitas')->default(4);
            // batas pemakaian normal; kalau lewat, meja ditandai overstay
            $table->unsignedSmallInteger('durasi_menit')->default(60);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index(['gudang_id', 'status_aktif'], 'meja_gudang_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meja');
    }
};
