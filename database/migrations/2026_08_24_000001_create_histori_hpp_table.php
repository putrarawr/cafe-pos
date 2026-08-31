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
        Schema::create('histori_hpp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('pembelian_id')->nullable()->constrained('pembelian')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('supplier')->nullOnDelete();
            $table->string('nomer_entry')->nullable();
            $table->dateTime('tanggal');
            $table->integer('stok_sebelum')->default(0);
            $table->integer('qty_beli')->default(0);
            $table->string('satuan')->nullable();
            $table->decimal('harga_beli_masuk', 15, 2)->default(0);
            $table->decimal('hpp_sebelum', 15, 2)->default(0);
            $table->decimal('hpp_sesudah', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histori_hpp');
    }
};
