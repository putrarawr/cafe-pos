<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomer_entry')->unique();
            $table->foreignId('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->integer('total')->default(0);
            $table->integer('diskon')->default(0);
            $table->integer('neto')->default(0);
            $table->string('jenis_pembayaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian');
    }
};
