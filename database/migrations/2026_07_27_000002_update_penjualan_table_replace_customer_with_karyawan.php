<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (Schema::hasColumn('penjualan', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (!Schema::hasColumn('penjualan', 'karyawan_id')) {
                $table->foreignId('karyawan_id')->nullable()->after('nomer_nota')->constrained('karyawan', 'id_karyawan')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (Schema::hasColumn('penjualan', 'karyawan_id')) {
                $table->dropForeign(['karyawan_id']);
                $table->dropColumn('karyawan_id');
            }
            if (!Schema::hasColumn('penjualan', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('nomer_nota')->constrained('customers', 'id_customer')->nullOnDelete();
            }
        });
    }
};
