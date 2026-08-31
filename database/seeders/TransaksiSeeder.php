<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\DetailBeli;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\Karyawan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PerpindahanBarang;
use App\Models\PerpindahanBarangDetail;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransaksiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stokService = app(StokService::class);
        $admin = User::first();
        $gudangUtama = Gudang::where('nama_gudang', 'like', '%Utama%')->first() ?? Gudang::first();
        $gudangBarat = Gudang::where('nama_gudang', 'like', '%Barat%')->first() ?? Gudang::skip(1)->first();
        $gudangTimur = Gudang::where('nama_gudang', 'like', '%Timur%')->first() ?? Gudang::skip(2)->first();

        $supplierIndofood = Supplier::where('nama_supplier', 'like', '%Indofood%')->first();
        $supplierGudangGaram = Supplier::where('nama_supplier', 'like', '%Gudang Garam%')->first();
        $supplierUnilever = Supplier::where('nama_supplier', 'like', '%Unilever%')->first();

        // --------------------------------------------------------------------------------
        // 1. SIMULASI TRANSAKSI PEMBELIAN (Barang Masuk -> Kartu Stok Masuk & Histori HPP)
        // --------------------------------------------------------------------------------
        $barangIndomie = Barang::where('nama_barang', 'like', '%Indomie Goreng%')->first();
        $barangTehBotol = Barang::where('nama_barang', 'like', '%Teh Botol Sosro%')->first();
        $barangRokokGG = Barang::where('nama_barang', 'like', '%Garam Gudang Filter 12%')->first();
        $barangSabun = Barang::where('nama_barang', 'like', '%Sabun Lifebuoy%')->first();

        // Pembelian 1: 10 Hari lalu dari Indofood
        if ($barangIndomie && $supplierIndofood && $gudangUtama) {
            $tgl1 = Carbon::now()->subDays(10)->setTime(9, 30);
            $entry1 = 'PB-' . $tgl1->format('Ymd') . '-0001';
            if (!Pembelian::where('nomer_entry', $entry1)->exists()) {
                $pembelian1 = Pembelian::create([
                    'user_id' => $admin?->id ?? 1,
                    'supplier_id' => $supplierIndofood->id,
                    'gudang_id' => $gudangUtama->id,
                    'nomer_entry' => $entry1,
                    'tanggal' => $tgl1->toDateString(),
                    'total' => 1100000,
                    'diskon' => 0,
                    'neto' => 1100000,
                    'jenis_pembayaran' => 'transfer',
                    'keterangan' => 'Restock rutin bulanan',
                    'created_at' => $tgl1,
                    'updated_at' => $tgl1,
                ]);

                DetailBeli::create([
                    'pembelian_id' => $pembelian1->id,
                    'barang_id' => $barangIndomie->id,
                    'jumlah' => 10,
                    'satuan' => 'Dus', // 10 Dus = 400 Bungkus @ Rp 2.750
                    'harga' => 110000,
                    'subtotal' => 1100000,
                ]);

                $stokService->terapkanPembelian($pembelian1);
            }
        }

        // Pembelian 2: 7 Hari lalu dari Gudang Garam
        if ($barangRokokGG && $supplierGudangGaram && $gudangUtama) {
            $tgl2 = Carbon::now()->subDays(7)->setTime(11, 15);
            $entry2 = 'PB-' . $tgl2->format('Ymd') . '-0001';
            if (!Pembelian::where('nomer_entry', $entry2)->exists()) {
                $pembelian2 = Pembelian::create([
                    'user_id' => $admin?->id ?? 1,
                    'supplier_id' => $supplierGudangGaram->id,
                    'gudang_id' => $gudangUtama->id,
                    'nomer_entry' => $entry2,
                    'tanggal' => $tgl2->toDateString(),
                    'total' => 2180000,
                    'diskon' => 0,
                    'neto' => 2180000,
                    'jenis_pembayaran' => 'tempo',
                    'keterangan' => 'Pengadaan stok rokok',
                    'created_at' => $tgl2,
                    'updated_at' => $tgl2,
                ]);

                DetailBeli::create([
                    'pembelian_id' => $pembelian2->id,
                    'barang_id' => $barangRokokGG->id,
                    'jumlah' => 10,
                    'satuan' => 'Pres/Slof', // 10 Slof = 100 Bungkus @ Rp 21.800
                    'harga' => 218000,
                    'subtotal' => 2180000,
                ]);

                $stokService->terapkanPembelian($pembelian2);
            }
        }

        // Pembelian 3: 4 Hari lalu dari Unilever
        if ($barangSabun && $supplierUnilever && $gudangUtama) {
            $tgl3 = Carbon::now()->subDays(4)->setTime(14, 0);
            $entry3 = 'PB-' . $tgl3->format('Ymd') . '-0001';
            if (!Pembelian::where('nomer_entry', $entry3)->exists()) {
                $pembelian3 = Pembelian::create([
                    'user_id' => $admin?->id ?? 1,
                    'supplier_id' => $supplierUnilever->id,
                    'gudang_id' => $gudangUtama->id,
                    'nomer_entry' => $entry3,
                    'tanggal' => $tgl3->toDateString(),
                    'total' => 940000,
                    'diskon' => 0,
                    'neto' => 940000,
                    'jenis_pembayaran' => 'tunai',
                    'keterangan' => 'Pengadaan sabun mandi',
                    'created_at' => $tgl3,
                    'updated_at' => $tgl3,
                ]);

                DetailBeli::create([
                    'pembelian_id' => $pembelian3->id,
                    'barang_id' => $barangSabun->id,
                    'jumlah' => 5,
                    'satuan' => 'Dus', // 5 Dus = 240 Batang @ Rp 3.900
                    'harga' => 188000,
                    'subtotal' => 940000,
                ]);

                $stokService->terapkanPembelian($pembelian3);
            }
        }

        // --------------------------------------------------------------------------------
        // 2. SIMULASI MUTASI STOK ANTAR GUDANG (PerpindahanBarang & Kartu Stok)
        // --------------------------------------------------------------------------------
        if ($barangIndomie && $gudangUtama && $gudangBarat) {
            $tglMutasi = Carbon::now()->subDays(5)->setTime(10, 0);
            $entryMutasi = 'MB-' . $tglMutasi->format('Ymd') . '-0001';
            if (!PerpindahanBarang::where('nomer_entry', $entryMutasi)->exists()) {
                $mutasi1 = PerpindahanBarang::create([
                    'user_id' => $admin?->id ?? 1,
                    'gudang_asal_id' => $gudangUtama->id,
                    'gudang_tujuan_id' => $gudangBarat->id,
                    'nomer_entry' => $entryMutasi,
                    'tanggal' => $tglMutasi->toDateString(),
                    'keterangan' => 'Distribusi stok ke Cabang Barat',
                    'created_at' => $tglMutasi,
                    'updated_at' => $tglMutasi,
                ]);

                PerpindahanBarangDetail::create([
                    'perpindahan_barang_id' => $mutasi1->id,
                    'barang_id' => $barangIndomie->id,
                    'jumlah' => 80,
                ]);

                // Mutasi keluar dari gudang utama
                $stokService->kurangiStok($barangIndomie->id, $gudangUtama->id, 80, [
                    'nomer_entry' => $mutasi1->nomer_entry,
                    'tanggal' => $mutasi1->tanggal,
                    'keterangan' => "Mutasi ke {$gudangBarat->nama_gudang}",
                ]);

                // Mutasi masuk ke gudang barat
                $stokService->tambahStok($barangIndomie->id, $gudangBarat->id, 80, [
                    'nomer_entry' => $mutasi1->nomer_entry,
                    'tanggal' => $mutasi1->tanggal,
                    'keterangan' => "Mutasi dari {$gudangUtama->nama_gudang}",
                ]);
            }
        }

        // --------------------------------------------------------------------------------
        // 3. SIMULASI TRANSAKSI PENJUALAN KASIR (Penjualan, DetailJual, Promo Bonus, Kartu Stok)
        // --------------------------------------------------------------------------------
        $karyawans = Karyawan::all();
        $kasir1 = $karyawans->first();
        $kasir2 = $karyawans->skip(1)->first() ?? $kasir1;

        $daftarBarang = Barang::take(10)->get();

        for ($hari = 6; $hari >= 0; $hari--) {
            $tglJual = Carbon::now()->subDays($hari)->setTime(rand(9, 20), rand(10, 50));
            $karyawanAktif = ($hari % 2 === 0) ? $kasir1 : $kasir2;
            $gudangJual = $gudangUtama;

            $seq = Penjualan::whereDate('tanggal', $tglJual->toDateString())->count() + 1;
            $nomerNota = 'PJ-' . $tglJual->format('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            if (Penjualan::where('nomer_nota', $nomerNota)->exists()) {
                $nomerNota .= '-' . rand(100, 999);
            }
            $jenisBayar = match ($hari % 3) {
                0 => 'tunai',
                1 => 'qris',
                default => 'transfer',
            };

            // Simulasi penjualan 2-3 barang per transaksi
            $selectedBarangs = $daftarBarang->random(min(3, $daftarBarang->count()));
            $totalTrx = 0;
            $itemsDetail = [];

            foreach ($selectedBarangs as $b) {
                $qty = rand(1, 4);
                // Cek promo khusus: jika barang ini Indomie dan beli 10, bonus Teh Botol
                $hargaSatuan = $b->getHargaTierForQty($qty, $b->satuan);
                $subtotal = $hargaSatuan * $qty;
                $totalTrx += $subtotal;

                $itemsDetail[] = [
                    'barang' => $b,
                    'satuan' => $b->satuan ?? 'Pcs',
                    'jumlah' => $qty,
                    'harga' => $hargaSatuan,
                    'hpp' => $b->getHppForSatuan($b->satuan),
                    'diskon' => max(0, ((int)$b->harga_jual - $hargaSatuan) * $qty),
                    'subtotal' => $subtotal,
                    'is_bonus' => false,
                ];
            }

            // Jika ada promo Indomie pada hari ini, tambahkan item bonus gratis
            if ($hari === 1 && $barangTehBotol) {
                $itemsDetail[] = [
                    'barang' => $barangTehBotol,
                    'satuan' => $barangTehBotol->satuan ?? 'Botol',
                    'jumlah' => 1,
                    'harga' => 0,
                    'hpp' => $barangTehBotol->getHppForSatuan($barangTehBotol->satuan),
                    'diskon' => 0,
                    'subtotal' => 0,
                    'is_bonus' => true,
                ];
            }

            $diskonTrx = ($hari === 2) ? 5000 : 0; // diskon kupon
            $neto = max(0, $totalTrx - $diskonTrx);
            $bayar = ($jenisBayar === 'tunai') ? ceil($neto / 10000) * 10000 : $neto;

            $penjualan = Penjualan::create([
                'nomer_nota' => $nomerNota,
                'karyawan_id' => $karyawanAktif?->id_karyawan,
                'user_id' => null,
                'gudang_id' => $gudangJual->id,
                'tanggal' => $tglJual->toDateString(),
                'total' => $totalTrx,
                'diskon' => $diskonTrx,
                'neto' => $neto,
                'jenis_pembayaran' => $jenisBayar,
                'bayar' => $bayar,
                'kembalian' => max(0, $bayar - $neto),
                'created_at' => $tglJual,
                'updated_at' => $tglJual,
            ]);

            foreach ($itemsDetail as $d) {
                DetailJual::create([
                    'penjualan_id' => $penjualan->id,
                    'barang_id' => $d['barang']->id,
                    'gudang_id' => $gudangJual->id,
                    'satuan' => $d['satuan'],
                    'jumlah' => $d['jumlah'],
                    'harga' => $d['harga'],
                    'hpp' => $d['hpp'],
                    'diskon' => $d['diskon'],
                    'subtotal' => $d['subtotal'],
                    'is_bonus' => $d['is_bonus'],
                ]);

                // Kurangi stok & catat kartu stok keluar
                $faktor = $d['barang']->getFaktorKonversi($d['satuan']);
                $jumlahDasar = $d['jumlah'] * $faktor;

                $stokService->kurangiStok($d['barang']->id, $gudangJual->id, $jumlahDasar, [
                    'nomer_entry' => $penjualan->nomer_nota,
                    'tanggal' => $tglJual,
                    'harga' => $d['harga'],
                    'keterangan' => $d['is_bonus']
                        ? "Bonus promo penjualan ({$d['jumlah']} {$d['satuan']})"
                        : "Penjualan nota {$penjualan->nomer_nota} ({$d['jumlah']} {$d['satuan']})",
                ]);
            }
        }
    }
}
