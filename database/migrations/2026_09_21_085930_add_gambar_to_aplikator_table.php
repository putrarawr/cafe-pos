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
        Schema::table('aplikator', function (Blueprint $table) {
            $table->string('gambar')->nullable()->after('persentase_komisi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplikator', function (Blueprint $table) {
            $table->dropColumn('gambar');
        });
    }
};
