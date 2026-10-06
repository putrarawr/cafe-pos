<?php

use App\Http\Controllers\KasirController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- Kasir Auth ---
Route::get('/kasir/login', [KasirController::class, 'showLogin'])->name('kasir.login');
Route::post('/kasir/login', [KasirController::class, 'login'])->name('kasir.login.submit');
Route::post('/kasir/logout', [KasirController::class, 'logout'])->name('kasir.logout');

// --- Kasir (harus login: Karyawan atau Admin/User) ---
Route::middleware('auth:karyawan,web')->group(function () {
    Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');
    Route::get('/kasir/data', [KasirController::class, 'data'])->name('kasir.data');
    Route::get('/kasir/riwayat', [KasirController::class, 'riwayat'])->name('kasir.riwayat');
    Route::post('/kasir/riwayat/{id}/cetak', [KasirController::class, 'verifikasiCetak'])->name('kasir.riwayat.cetak');
    Route::post('/kasir/simpan', [KasirController::class, 'simpan'])->name('kasir.simpan');

    // --- Order Pending (keranjang ditahan, stok di-reserve) ---
    Route::get('/kasir/order-pending', [KasirController::class, 'daftarOrderPending'])->name('kasir.order-pending.index');
    Route::post('/kasir/order-pending', [KasirController::class, 'simpanOrderPending'])->name('kasir.order-pending.simpan');
    Route::get('/kasir/order-pending/{id}', [KasirController::class, 'detailOrderPending'])->name('kasir.order-pending.detail');
    Route::post('/kasir/order-pending/{id}/batal', [KasirController::class, 'batalkanOrderPending'])->name('kasir.order-pending.batal');

    // --- Meja (dashboard pilih meja dine in) ---
    Route::get('/kasir/meja', [KasirController::class, 'daftarMeja'])->name('kasir.meja.index');
    Route::post('/kasir/meja/{id}/lepas', [KasirController::class, 'lepasMeja'])->name('kasir.meja.lepas');
});

