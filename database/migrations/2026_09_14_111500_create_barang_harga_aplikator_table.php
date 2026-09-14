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
        Schema::create('barang_harga_aplikator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('aplikator_id')->constrained('aplikator')->cascadeOnDelete();
            $table->unsignedBigInteger('harga_jual')->default(0);
            $table->timestamps();

            $table->unique(['barang_id', 'aplikator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang_harga_aplikator');
    }
};
