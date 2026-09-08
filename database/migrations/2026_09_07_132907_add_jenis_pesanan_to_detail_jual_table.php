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
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->enum('jenis_pesanan', ['dine_in', 'take_away', 'delivery'])
                ->default('dine_in')
                ->after('satuan');
            $table->index('jenis_pesanan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropIndex(['jenis_pesanan']);
            $table->dropColumn('jenis_pesanan');
        });
    }
};