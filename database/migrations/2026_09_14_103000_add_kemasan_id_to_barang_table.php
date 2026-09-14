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
        Schema::table('barang', function (Blueprint $table) {
            if (!Schema::hasColumn('barang', 'kemasan_id')) {
                $table->foreignId('kemasan_id')
                    ->nullable()
                    ->after('tipe_barang')
                    ->constrained('barang')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            if (Schema::hasColumn('barang', 'kemasan_id')) {
                $table->dropForeign(['kemasan_id']);
                $table->dropColumn('kemasan_id');
            }
        });
    }
};
