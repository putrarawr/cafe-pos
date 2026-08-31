<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: changing column types requires doctrine/dbal package installed in Laravel.
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'no_telp')) {
                $table->string('no_telp')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'no_telp')) {
                $table->integer('no_telp')->nullable()->change();
            }
        });
    }
};
