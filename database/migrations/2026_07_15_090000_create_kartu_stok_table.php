<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->string('nomer_entry')->nullable();
            $table->dateTime('tanggal');
            $table->string('keterangan')->nullable();
            $table->string('jenis_transaksi'); // konstanta di model: masuk|keluar|pindah_masuk|pindah_keluar|koreksi
            $table->integer('jumlah');
            $table->integer('harga')->default(0);
            $table->integer('saldo'); // stok setelah transaksi ini
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_stok');
    }
};
