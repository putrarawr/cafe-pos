<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // polanya sama kayak tabel pembelian, tapi arah keluar (jualan ke customer)
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('nomer_nota')->unique();
            // nullable: boleh penjualan umum tanpa pilih customer
            $table->foreignId('customer_id')->nullable()->constrained('customers', 'id_customer')->nullOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->date('tanggal')->nullable();
            $table->integer('total')->default(0);
            $table->integer('diskon')->default(0);
            $table->integer('neto')->default(0);
            $table->string('jenis_pembayaran')->nullable();
            $table->integer('bayar')->default(0);
            $table->integer('kembalian')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};
