<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perpindahan_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perpindahan_barang_id')->constrained('perpindahan_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perpindahan_barang_detail');
    }
};
