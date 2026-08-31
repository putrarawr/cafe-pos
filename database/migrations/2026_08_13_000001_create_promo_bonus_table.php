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
        Schema::create('promo_bonus', function (Blueprint $table) {
            $table->id();
            $table->string('nama_promo');
            $table->foreignId('barang_utama_id')->constrained('barang')->cascadeOnDelete();
            $table->integer('min_qty_utama')->default(1);
            $table->string('satuan_utama')->nullable();
            $table->foreignId('barang_bonus_id')->constrained('barang')->cascadeOnDelete();
            $table->integer('qty_bonus')->default(1);
            $table->string('satuan_bonus')->nullable();
            $table->boolean('is_kelipatan')->default(true);
            $table->boolean('is_aktif')->default(true);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_bonus');
    }
};
