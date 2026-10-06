<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu periode pemakaian satu meja (linimasa + durasi).
        // Hanya status 'terisi' yang mengunci meja; begitu dibayar atau dibatalkan,
        // sesi ditutup dan meja langsung bisa dipilih lagi.
        //
        // Sengaja tabel TERPISAH dari `penjualan`, bukan kolom status di sana:
        // nomor nota penjualan dihitung dari COUNT(*) per tanggal, jadi baris
        // draf di `penjualan` akan menggeser semua nomor nota berikutnya.
        Schema::create('sesi_meja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meja_id')->constrained('meja')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawan', 'id_karyawan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['terisi', 'selesai', 'batal'])->default('terisi');
            $table->dateTime('mulai')->index();
            $table->dateTime('selesai')->nullable();
            // snapshot meja.durasi_menit saat sesi dibuka, jadi mengubah durasi
            // di master tidak mengubah sesi yang sedang berjalan
            $table->unsignedSmallInteger('batas_menit')->default(60);
            $table->unsignedInteger('durasi_menit_terpakai')->default(0);
            $table->boolean('overstay')->default(false);
            $table->foreignId('order_pending_id')->nullable()->constrained('order_pending')->nullOnDelete();
            $table->foreignId('penjualan_id')->nullable()->constrained('penjualan')->nullOnDelete();
            $table->timestamps();

            $table->index(['meja_id', 'status'], 'sesi_meja_meja_status_index');
        });

        // Aturan inti fitur: satu meja hanya boleh punya satu sesi terisi.
        // Partial unique index ditegakkan DATABASE, bukan sekadar disable tombol
        // di UI, jadi dua kasir yang klik bersamaan tetap tidak bisa dobel-booking.
        DB::statement(
            "CREATE UNIQUE INDEX sesi_meja_aktif_unik ON sesi_meja (meja_id) WHERE status = 'terisi'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_meja');
    }
};
