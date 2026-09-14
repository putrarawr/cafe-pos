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
        Schema::create('aplikator', function (Blueprint $table) {
            $table->id();
            $table->string('nama_aplikator');
            $table->string('kode_aplikator')->nullable()->unique();
            $table->decimal('persentase_komisi', 5, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aplikator');
    }
};
