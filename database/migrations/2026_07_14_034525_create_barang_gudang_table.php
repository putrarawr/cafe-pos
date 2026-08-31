<?php

// Pivot Table (Tabel Penghubung)

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
    Schema::create('barang_gudang', function (Blueprint $table) {
        $table->id();
        $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
        $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
        $table->integer('stok')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang_gudang');
    }
};
