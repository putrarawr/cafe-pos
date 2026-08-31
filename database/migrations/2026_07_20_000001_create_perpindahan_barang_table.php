<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perpindahan_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_asal_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('gudang_tujuan_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perpindahan_barang');
    }
};
