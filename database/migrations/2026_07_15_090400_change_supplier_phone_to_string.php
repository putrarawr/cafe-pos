<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: changing column types requires doctrine/dbal package installed in Laravel.
        Schema::table('supplier', function (Blueprint $table) {
            if (Schema::hasColumn('supplier', 'no_telepon')) {
                $table->string('no_telepon')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier', function (Blueprint $table) {
            if (Schema::hasColumn('supplier', 'no_telepon')) {
                $table->integer('no_telepon')->nullable()->change();
            }
        });
    }
};
