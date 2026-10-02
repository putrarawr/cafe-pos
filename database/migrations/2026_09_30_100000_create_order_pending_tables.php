<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Order pending = keranjang kasir yang "ditahan" (belum dibayar) tapi
        // stoknya sudah dikunci supaya order lain tidak bisa ambil barang yang sama.
        //
        // Sengaja tabel TERPISAH dari `penjualan`, bukan pakai kolom status:
        // nomor nota penjualan dihitung dari COUNT(*) penjualan per tanggal,
        // jadi kalau order pending ikut masuk ke `penjualan` semua nomor nota
        // setelahnya ikut bergeser.
        Schema::create('order_pending', function (Blueprint $table) {
            $table->id();
            $table->string('kode_order')->unique();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->enum('status', ['pending', 'selesai', 'batal'])->default('pending');
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawan', 'id_karyawan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal')->nullable();
            // snapshot nilai saat order ditahan, cuma untuk tampilan di daftar order
            $table->integer('diskon_persen')->default(0);
            $table->integer('total')->default(0);
            $table->integer('diskon')->default(0);
            $table->integer('neto')->default(0);
            $table->integer('biaya_kirim')->default(0);
            $table->text('alamat_pengiriman')->nullable();
            $table->foreignId('aplikator_id')->nullable()->constrained('aplikator')->nullOnDelete();
            $table->text('catatan')->nullable();
            // terisi setelah order dibayar, supaya order pending != penjualan
            $table->foreignId('penjualan_id')->nullable()->constrained('penjualan')->nullOnDelete();
            $table->timestamps();

            $table->index(['gudang_id', 'status'], 'order_pending_gudang_status_index');
        });

        // quantity order = "jumlah_dasar" (satuan paling dasar / level 1).
        // Semua aritmetika stok di aplikasi ini berbasis satuan dasar, jadi
        // qty yang di-reserve WAJIB disimpan dalam satuan dasar, bukan qty
        // sesuai satuan yang dipilih kasir (mis. 2 dus = 48 pcs).
        Schema::create('order_pending_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_pending_id')->constrained('order_pending')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            // gudang ikut di-snapshot supaya Summation reservasi tidak perlu join header
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('satuan')->nullable();
            $table->integer('jumlah')->default(0);
            $table->integer('jumlah_dasar')->default(0);
            // harga & diskon di sini hanya snapshot tampilan di daftar order;
            // harga yang dipakai transaksi dihitung ulang di server
            $table->integer('harga')->default(0);
            $table->integer('diskon')->default(0);
            $table->integer('subtotal')->default(0);
            $table->boolean('is_bonus')->default(false);
            $table->foreignId('promo_id')->nullable()->constrained('promo_bonus')->nullOnDelete();
            $table->enum('jenis_pesanan', ['dine_in', 'take_away', 'delivery'])->default('dine_in');
            $table->timestamps();

            $table->index(['barang_id', 'gudang_id'], 'order_pending_item_barang_gudang_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pending_item');
        Schema::dropIfExists('order_pending');
    }
};
