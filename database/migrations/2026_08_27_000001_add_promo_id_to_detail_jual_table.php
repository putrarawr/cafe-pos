<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->constrained('promo_bonus')->nullOnDelete()->after('is_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropForeign(['promo_id']);
            $table->dropColumn('promo_id');
        });
    }
};
