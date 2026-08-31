<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('detail_jual', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
};
