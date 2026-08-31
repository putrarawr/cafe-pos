// =====================================================================
// API ADAPTER KASIR (versi tanpa API / langsung nyambung admin Filament)
// ---------------------------------------------------------------------
// Semua akses data kasir lewat file ini, jadi UI (kasir.js) gak perlu
// tau datanya dari mana.
//
// CARA KERJANYA SEKARANG:
//   - Data awal (barang, gudang, jenis) DISUNTIK dari
//     KasirController lewat window.KASIR_DATA di kasir.blade.php.
//     Gak ada fetch GET sama sekali.
//   - Simpan transaksi = POST ke route web biasa (/kasir/simpan),
//     bukan routes/api.php. CSRF udah otomatis keikut.
//   - Kalau window.KASIR_DATA gak ada (misal file dibuka tanpa Laravel),
//     otomatis jatuh ke MOCK di bawah — jadi temen frontend tetep bisa
//     ngoding UI tanpa nunggu database keisi.
// =====================================================================

const SIMPAN_URL = '/kasir/simpan';

// true kalau Blade nyuntik data asli dari database
export const USE_MOCK = typeof window === 'undefined' || !window.KASIR_DATA;

// ---------------------------------------------------------------------
// MOCK DATA — dipakai cuma kalau gak ada suntikan dari server.
// Bentuknya sama persis dengan yang dikirim KasirController.
// ---------------------------------------------------------------------

const mockData = {
    jenisBarang: [
        { id: 1, nama_jenis: 'Minuman' },
        { id: 2, nama_jenis: 'Makanan' },
        { id: 3, nama_jenis: 'Sembako' },
        { id: 4, nama_jenis: 'Alat Tulis' },
    ],
    gudang: [
        { id: 1, nama_gudang: 'Gudang Utama', alamat: 'Jl. Raya No. 1' },
        { id: 2, nama_gudang: 'Rak Toko Depan', alamat: 'Area kasir' },
    ],
    // stok = { [gudang_id]: jumlah }, sama kayak pivot barang_gudang
    barang: [
        { id: 1, jenis_barang_id: 1, nama_barang: 'Kopi Susu Botol', barcode: '8991001000101', nomer_seri: 'MNM-0001', harga_jual: 8000, satuan: 'btl', stok: { 1: 40, 2: 12 }, units: [{ level: 1, satuan: 'btl', faktor: 1, isi_info: null, harga_jual: 8000 }, { level: 2, satuan: 'dus', faktor: 24, isi_info: '1 dus = 24 btl', harga_jual: 180000 }] },
        { id: 2, jenis_barang_id: 1, nama_barang: 'Teh Tarik Kotak', barcode: '8991001000202', nomer_seri: 'MNM-0002', harga_jual: 6500, satuan: 'pcs', stok: { 1: 25, 2: 8 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 6500 }, { level: 2, satuan: 'pack', faktor: 10, isi_info: '1 pack = 10 pcs', harga_jual: 60000 }] },
        { id: 3, jenis_barang_id: 1, nama_barang: 'Air Mineral 600ml', barcode: '8991001000303', nomer_seri: 'MNM-0003', harga_jual: 4000, satuan: 'btl', stok: { 1: 100, 2: 24 }, units: [{ level: 1, satuan: 'btl', faktor: 1, isi_info: null, harga_jual: 4000 }, { level: 2, satuan: 'dus', faktor: 24, isi_info: '1 dus = 24 btl', harga_jual: 90000 }] },
        { id: 4, jenis_barang_id: 2, nama_barang: 'Indomie Goreng', barcode: '8991001000404', nomer_seri: 'MKN-0001', harga_jual: 3500, satuan: 'pcs', stok: { 1: 200, 2: 40 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 3500 }, { level: 2, satuan: 'dus', faktor: 40, isi_info: '1 dus = 40 pcs', harga_jual: 135000 }] },
        { id: 5, jenis_barang_id: 2, nama_barang: 'Roti Sobek Coklat', barcode: '8991001000505', nomer_seri: 'MKN-0002', harga_jual: 12000, satuan: 'pcs', stok: { 1: 15, 2: 5 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 12000 }] },
        { id: 6, jenis_barang_id: 2, nama_barang: 'Biskuit Kaleng', barcode: '8991001000606', nomer_seri: 'MKN-0003', harga_jual: 25000, satuan: 'klg', stok: { 1: 10, 2: 2 }, units: [{ level: 1, satuan: 'klg', faktor: 1, isi_info: null, harga_jual: 25000 }] },
        { id: 7, jenis_barang_id: 3, nama_barang: 'Beras 5kg', barcode: '8991001000707', nomer_seri: 'SBK-0001', harga_jual: 68000, satuan: 'sak', stok: { 1: 30, 2: 0 }, units: [{ level: 1, satuan: 'sak', faktor: 1, isi_info: null, harga_jual: 68000 }] },
        { id: 8, jenis_barang_id: 3, nama_barang: 'Gula Pasir 1kg', barcode: '8991001000808', nomer_seri: 'SBK-0002', harga_jual: 16000, satuan: 'kg', stok: { 1: 50, 2: 10 }, units: [{ level: 1, satuan: 'kg', faktor: 1, isi_info: null, harga_jual: 16000 }, { level: 2, satuan: 'karung', faktor: 50, isi_info: '1 karung = 50 kg', harga_jual: 750000 }] },
        { id: 9, jenis_barang_id: 3, nama_barang: 'Minyak Goreng 1L', barcode: '8991001000909', nomer_seri: 'SBK-0003', harga_jual: 19000, satuan: 'btl', stok: { 1: 45, 2: 6 }, units: [{ level: 1, satuan: 'btl', faktor: 1, isi_info: null, harga_jual: 19000 }, { level: 2, satuan: 'dus', faktor: 12, isi_info: '1 dus = 12 btl', harga_jual: 220000 }] },
        { id: 10, jenis_barang_id: 4, nama_barang: 'Pulpen Hitam', barcode: '8991001001010', nomer_seri: 'ATK-0001', harga_jual: 3000, satuan: 'pcs', stok: { 1: 80, 2: 30 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 3000 }, { level: 2, satuan: 'box', faktor: 12, isi_info: '1 box = 12 pcs', harga_jual: 33000 }] },
        { id: 11, jenis_barang_id: 4, nama_barang: 'Buku Tulis 38 lbr', barcode: '8991001001111', nomer_seri: 'ATK-0002', harga_jual: 5000, satuan: 'pcs', stok: { 1: 60, 2: 20 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 5000 }, { level: 2, satuan: 'pack', faktor: 10, isi_info: '1 pack = 10 pcs', harga_jual: 45000 }] },
        { id: 12, jenis_barang_id: 4, nama_barang: 'Spidol Papan Tulis', barcode: '8991001001212', nomer_seri: 'ATK-0003', harga_jual: 9000, satuan: 'pcs', stok: { 1: 20, 2: 4 }, units: [{ level: 1, satuan: 'pcs', faktor: 1, isi_info: null, harga_jual: 9000 }] },
    ],
};

let mockCounter = 1;

// sumber data: suntikan server kalau ada, kalau nggak ya mock
const sumber = () => (USE_MOCK ? mockData : window.KASIR_DATA);

// ---------------------------------------------------------------------
// FUNGSI PUBLIK — dipanggil dari kasir.js (nama & bentuk return TETAP,
// jadi kasir.js gak perlu diubah sama sekali)
// ---------------------------------------------------------------------

export async function getBarang() {
    if (!USE_MOCK) {
        try {
            const res = await fetch('/kasir/data?_t=' + Date.now(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const fresh = await res.json();
                if (fresh && fresh.barang) {
                    // Gabungkan data segar ke data lama — jangan menimpa seluruh
                    // window.KASIR_DATA karena field karyawan/kasirList/toko hanya
                    // ada di suntikan awal (index) dan tidak dikirim oleh /kasir/data.
                    window.KASIR_DATA = { ...window.KASIR_DATA, ...fresh };
                    return fresh.barang;
                }
            }
        } catch (e) {
            console.warn('Menggunakan fallback window.KASIR_DATA:', e);
        }
    }
    return structuredClone(sumber().barang);
}

export async function getJenisBarang() {
    return structuredClone(sumber().jenisBarang);
}

export async function getGudang() {
    return structuredClone(sumber().gudang);
}

/**
 * Riwayat transaksi (default: hari ini). GET /kasir/riwayat.
 * Saat mode mock, kembalikan array kosong (sesi local tidak menyimpan riwayat).
 */
export async function getRiwayat(tanggal, { limit, before, kasir, gudang_id, metode } = {}) {
    if (USE_MOCK) {
        await new Promise((r) => setTimeout(r, 250));
        return { items: [], summary: { jumlah: 0, total_neto: 0 } };
    }

    const params = new URLSearchParams();
    if (tanggal) params.set('tanggal', tanggal);
    if (limit != null) params.set('limit', limit);
    if (before != null) params.set('before', before);
    if (kasir) params.set('kasir', kasir);
    if (gudang_id) params.set('gudang_id', gudang_id);
    if (metode) params.set('metode', metode);
    const url = `/kasir/riwayat${params.toString() ? `?${params.toString()}` : ''}`;

    const res = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (res.redirected && !res.ok) {
        throw new Error('Sesi berakhir. Silakan login ulang.');
    }
    if (!res.ok) {
        throw new Error(`Gagal memuat riwayat (${res.status})`);
    }
    return res.json();
}

/**
 * Verifikasi password untuk cetak ulang struk. POST /kasir/riwayat/{id}/cetak.
 * Password dicek di server terhadap password user yang sedang login.
 */
export async function verifikasiCetakUlang(penjualanId, password) {
    if (USE_MOCK) {
        await new Promise((r) => setTimeout(r, 300));
        if (password !== 'kasir123') {
            throw new Error('Password salah! Silakan coba lagi.');
        }
        throw new Error('Mode simulasi tidak menyimpan riwayat transaksi');
    }

    const res = await fetch(`/kasir/riwayat/${penjualanId}/cetak`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ password }),
    });

    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message ?? `Gagal verifikasi (${res.status})`);
    }
    return res.json();
}

/**
 * Simpan transaksi kasir → POST /kasir/simpan (route web biasa, bukan API).
 *
 * Yang dipakai server cuma: gudang_id, tanggal, diskon,
 * jenis_pembayaran, bayar, dan details[{barang_id, jumlah, diskon, satuan}].
 * Harga, subtotal, total, dan nomer nota DIHITUNG ULANG di server
 * (KasirController@simpan) biar gak bisa dimanipulasi dari browser.
 */
export async function simpanPenjualan(payload) {
    if (USE_MOCK) {
        // simulasi: kurangi stok mock & balikin nota bikinan lokal
        await new Promise((r) => setTimeout(r, 400));
        for (const d of payload.details) {
            const barang = mockData.barang.find((b) => b.id === d.barang_id);
            if (barang && barang.stok[d.gudang_id] != null) {
                barang.stok[d.gudang_id] -= d.jumlah;
            }
        }
        return { id: mockCounter, nomer_nota: buatNomerNota(mockCounter++), ...payload };
    }

    const res = await fetch(SIMPAN_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(payload),
    });

    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message ?? `Gagal menyimpan (${res.status})`);
    }
    return res.json();
}

/** Nomer nota sementara buat mode mock. Kalau nyambung server, server yang bikin. */
export function buatNomerNota(urutan = 1) {
    const now = new Date();
    const ymd = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('');
    return `PJ-${ymd}-${String(urutan).padStart(4, '0')}`;
}
