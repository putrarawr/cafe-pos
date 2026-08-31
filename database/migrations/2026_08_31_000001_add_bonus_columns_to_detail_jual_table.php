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
            $table->foreignId('bonus_barang_id')->nullable()->after('promo_id')->constrained('barang')->nullOnDelete();
            $table->integer('bonus_qty')->nullable()->after('bonus_barang_id');
            $table->string('bonus_satuan')->nullable()->after('bonus_qty');
            $table->decimal('bonus_hpp', 15, 2)->nullable()->after('bonus_satuan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropForeign(['bonus_barang_id']);
            $table->dropColumn(['bonus_barang_id', 'bonus_qty', 'bonus_satuan', 'bonus_hpp']);
        });
    }
};
