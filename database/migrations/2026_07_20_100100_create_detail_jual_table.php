<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // polanya sama kayak detail_beli
        Schema::create('detail_jual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('satuan')->nullable();
            $table->integer('jumlah')->default(0);
            $table->integer('harga')->default(0);
            $table->integer('diskon')->default(0);
            $table->integer('subtotal')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_jual');
    }
};
