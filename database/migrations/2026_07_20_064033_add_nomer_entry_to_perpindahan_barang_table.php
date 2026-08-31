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
        Schema::table('perpindahan_barang', function (Blueprint $table) {
            $table->string('nomer_entry')->unique()->after('id')->nullable(); // Initially nullable in case there is existing data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('perpindahan_barang', function (Blueprint $table) {
            $table->dropColumn('nomer_entry');
        });
    }
};
