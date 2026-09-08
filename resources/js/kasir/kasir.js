// =====================================================================
// LOGIC KASIR (POS)
// ---------------------------------------------------------------------
// File ini cuma ngurusin tampilan + keranjang. Semua data lewat api.js,
// jadi pas backend temenmu jadi tinggal ubah USE_MOCK di api.js.
// =====================================================================

import {
    getBarang,
    getJenisBarang,
    getGudang,
    getRiwayat,
    simpanPenjualan,
    verifikasiCetakUlang,
    USE_MOCK,
} from './api.js';

// ------------------------- STATE -------------------------

const state = {
    barang: [],
    jenisBarang: [],
    gudang: [],
    // keranjang: [{ barang_id, nama_barang, satuan, harga, harga_asli, jumlah, diskon, jenis_pesanan }]
    // harga_asli = harga normal per satuan, diskon = potongan rupiah dari harga bertingkat
    cart: [],
    gudangId: null,
    filterJenis: null,
    search: '',
    diskonTransaksi: 0,
    jenisPembayaran: 'tunai',
    bankTransfer: 'BCA',
    bayar: 0,
    isUangPas: false,
    paymentExpanded: true,
    alamatPengiriman: '',
    biayaKirim: 0,
    jenisPesanan: 'dine_in',
};

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

let searchRenderTimer = null;
function scheduleRenderProduk() {
    clearTimeout(searchRenderTimer);
    searchRenderTimer = setTimeout(() => renderProduk(), 180);
}

const TOKO_DEFAULT = { nama: 'Toko PKL', alamat: '', kontak: '' };

/**
 * Generates multi-tier unit options (Pcs, Pack, Dus, Slop, Bal, Bag, Karung) and wholesale prices for every item
 */
function getUnitsForBarang(barang) {
    if (barang && barang.units && barang.units.length > 0) {
        return barang.units;
    }

    return [{
        level: 1,
        satuan: barang?.satuan ?? 'Pcs',
        faktor: 1,
        harga_jual: Number(barang?.harga_jual || 0),
    }];
}

// ------------------------- HITUNGAN -------------------------

function subtotalItem(item) {
    return (item.harga_asli ?? item.harga) * item.jumlah - item.diskon;
}

function totalKotor() {
    return state.cart.reduce((sum, item) => sum + subtotalItem(item), 0);
}

// Total harga normal (sebelum potongan harga bertingkat) dan total potongan barang
function totalNormal() {
    return state.cart.reduce((sum, item) => sum + (item.harga_asli ?? item.harga) * item.jumlah, 0);
}

function totalPotonganBarang() {
    return state.cart.reduce((sum, item) => sum + Number(item.diskon || 0), 0);
}

// Potongan harga bertingkat ditampilkan dalam persen (potongan rupiah / total normal)
function persenPotongan(potongan, normal) {
    if (!Number(normal)) return 0;
    return Math.round((Number(potongan || 0) / Number(normal)) * 100);
}

function nominalDiskon() {
    const total = totalKotor();
    return Math.floor((total * state.diskonTransaksi) / 100);
}

function totalNeto() {
    return Math.max(0, totalKotor() - nominalDiskon() + (state.biayaKirim || 0));
}

function kembalian() {
    return Math.max(0, state.bayar - totalNeto());
}

function stokBarang(barang) {
    if (!state.gudangId) return 0;
    return barang.stok?.[state.gudangId] ?? 0;
}

function stokTersedia(barang) {
    const dasar = stokBarang(barang);
    const lines = state.cart.filter((i) => i.barang_id === barang.id);
    if (!lines.length) return dasar;
    const units = getUnitsForBarang(barang);
    const dipakai = lines.reduce((sum, item) => {
        const uObj = units.find((u) => u.satuan === item.satuan);
        return sum + item.jumlah * (uObj ? uObj.faktor : 1);
    }, 0);
    return Math.max(0, dasar - dipakai);
}

function namaGudangSekarang() {
    return state.gudang.find((g) => g.id === state.gudangId)?.nama_gudang ?? 'gudang ini';
}

function gudangStokTersedia(barang) {
    return state.gudang
        .filter((g) => (barang.stok?.[g.id] ?? 0) > 0)
        .map((g) => g.nama_gudang);
}

/**
 * Group cart items by jenis_pesanan
 * Returns object: { dine_in: [], take_away: [], delivery: [] }
 */
function getOrderGroups() {
    const groups = {
        dine_in: [],
        take_away: [],
        delivery: [],
    };
    state.cart.forEach((item) => {
        const tipe = item.jenis_pesanan || 'dine_in';
        if (groups[tipe]) {
            groups[tipe].push(item);
        } else {
            groups.dine_in.push(item);
        }
    });
    return groups;
}

// ------------------------- KERANJANG -------------------------

const cartKey = (barangId, jenis) => `${barangId}:${jenis || 'dine_in'}`;

function findCartLineKey(barangId) {
    const lines = state.cart.filter((i) => i.barang_id === barangId && !i.is_bonus);
    if (!lines.length) return null;
    const samaTipe = lines.find((i) => i.jenis_pesanan === state.jenisPesanan);
    return samaTipe?.key ?? lines[0].key;
}

function tambahKeCart(barangId) {
    const barang = state.barang.find((b) => b.id === barangId);
    if (!barang) return;

    if (barang.status === 'habis') {
        toast(`Menu ${barang.nama_barang} sedang tidak tersedia (habis)`, true);
        return;
    }

    const units = getUnitsForBarang(barang);
    const existing = state.cart.find(
        (i) => i.barang_id === barangId && i.jenis_pesanan === state.jenisPesanan && !i.is_bonus
    );
    const satuanDefault = existing ? existing.satuan : units[0].satuan;
    const unitObj = units.find((u) => u.satuan === satuanDefault) ?? units[0];
    const faktor = unitObj ? unitObj.faktor : 1;
    const hargaUnit = unitObj ? unitObj.harga_jual : barang.harga_jual;

    const tersedia = stokTersedia(barang);

    if (faktor > tersedia) {
        const stokDasar = stokBarang(barang);
        const rekomendasi = gudangStokTersedia(barang).filter((n) => n !== namaGudangSekarang());
        if (tersedia <= 0 && stokDasar > 0) {
            toast(`Semua stok ${barang.nama_barang} (${stokDasar} ${barang.satuan ?? ''}) sudah ada di keranjang`, true);
        } else if (stokDasar === 0) {
            if (rekomendasi.length > 0) {
                toast(`Stok ${barang.nama_barang} habis di ${namaGudangSekarang()}. Tersedia di: ${rekomendasi.join(', ')}`, true);
            } else {
                toast(`Stok ${barang.nama_barang} habis di semua gudang`, true);
            }
        } else {
            toast(`Stok ${barang.nama_barang} di ${namaGudangSekarang()} tinggal ${tersedia} ${barang.satuan ?? ''}`, true);
        }
        return;
    }

    if (existing) {
        existing.jumlah += 1;
    } else {
        state.cart.push({
            barang_id: barang.id,
            nama_barang: barang.nama_barang,
            key: cartKey(barang.id, state.jenisPesanan),
            satuan: satuanDefault,
            harga: hargaUnit,
            harga_asli: hargaUnit,
            jumlah: 1,
            diskon: 0,
            jenis_pesanan: state.jenisPesanan,
        });
    }
    render();
}

function ubahSatuanItem(key, satuanBaru) {
    const item = state.cart.find((i) => i.key === key);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === item.barang_id);
    if (!barang) return;

    const units = getUnitsForBarang(barang);
    const unitObj = units.find((u) => u.satuan === satuanBaru);
    if (!unitObj) return;

    const tersedia = stokTersedia(barang);
    const jumlahDasarBaru = item.jumlah * unitObj.faktor;
    const jumlahDasarLama = item.jumlah * (units.find((u) => u.satuan === item.satuan)?.faktor ?? 1);

    if (jumlahDasarBaru - jumlahDasarLama > tersedia) {
        toast(`Stok ${barang.nama_barang} tidak cukup untuk ${item.jumlah} ${unitObj.satuan} (tersedia: ${tersedia} ${barang.satuan ?? 'pcs'})`, true);
        return;
    }

    item.satuan = unitObj.satuan;
    item.harga = unitObj.harga_jual;
    render();
}

function ubahJumlah(key, delta) {
    const item = state.cart.find((i) => i.key === key);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === item.barang_id);
    const units = barang ? getUnitsForBarang(barang) : [];
    const unitObj = units.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;
    const baruJumlah = item.jumlah + delta;
    const tersedia = barang ? stokTersedia(barang) : Infinity;
    if (delta > 0 && delta * faktor > tersedia) {
        toast(`Stok ${barang?.nama_barang ?? ''} tinggal ${tersedia} ${barang?.satuan ?? ''}`, true);
        return;
    }
    if (baruJumlah <= 0) {
        state.cart = state.cart.filter((i) => i.key !== key);
    } else {
        item.jumlah = baruJumlah;
    }
    render();
}

/**
 * Ubah tipe pesanan GLOBAL (default untuk item BARU).
 * Item yang sudah ada di keranjang TIDAK diubah tipenya — tipe lama tetap.
 */
function setJenisPesananGlobal(tipe) {
    const validTypes = ['dine_in', 'take_away', 'delivery'];
    if (!validTypes.includes(tipe) || tipe === state.jenisPesanan) return;

    state.jenisPesanan = tipe;

    if (tipe !== 'delivery' && !state.cart.some((i) => i.jenis_pesanan === 'delivery')) {
        state.alamatPengiriman = '';
        state.biayaKirim = 0;
    }

    render();
}

function hapusItem(key) {
    state.cart = state.cart.filter((i) => i.key !== key);
    render();
}

let pendingHapusId = null;
let pendingNontunaiPayload = null;
let pendingDiskonPayload = null;
const DISKON_BESAR_PERSEN = 30;
const collapsedOrderGroups = new Set();

function mintaHapusItem(key) {
    const item = state.cart.find((i) => i.key === key);
    if (!item) return;
    pendingHapusId = key;
    const label = document.getElementById('label-hapus-item');
    if (label) label.textContent = `${item.nama_barang} (${item.jumlah} × ${rupiah(item.harga)})`;
    document.getElementById('modal-konfirmasi-hapus')?.classList.remove('hidden');
    document.getElementById('btn-batal-hapus')?.focus();
}

function tutupModalHapus() {
    pendingHapusId = null;
    document.getElementById('modal-konfirmasi-hapus')?.classList.add('hidden');
    fokusCartRow();
}

function setJumlah(key, jumlah) {
    const item = state.cart.find((i) => i.key === key);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === item.barang_id);
    const units = barang ? getUnitsForBarang(barang) : [];
    const unitObj = units.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;

    let parsed = Math.floor(Number(jumlah));
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return;
    }
    const tersedia = barang ? stokTersedia(barang) : Infinity;
    let baru = parsed;
    if ((baru - item.jumlah) * faktor > tersedia) {
        const maksTotal = item.jumlah + Math.floor(tersedia / faktor);
        toast(`Stok maksimal ${maksTotal} ${barang?.satuan ?? ''}`, true);
        baru = maksTotal;
    }
    item.jumlah = baru;
    render();
}

function resetTransaksi() {
    state.cart = [];
    state.diskonTransaksi = 0;
    state.bayar = 0;
    state.isUangPas = false;
    state.jenisPembayaran = 'tunai';
    state.bankTransfer = 'BCA';
    state.alamatPengiriman = '';
    state.biayaKirim = 0;
    state.jenisPesanan = 'dine_in';
    collapsedOrderGroups.clear();
    bonusToastShown.clear();
    document.querySelectorAll('input[name="bank_transfer"]').forEach((radio) => {
        radio.checked = radio.value === 'BCA';
    });
    renderPaymentMethodPills();
    render();
}

function togglePaymentDetails(forceState) {
    const container = document.getElementById('payment-details-container');
    const chevron = document.getElementById('icon-toggle-payment');
    if (!container) return;

    if (forceState !== undefined) {
        state.paymentExpanded = forceState;
    } else {
        state.paymentExpanded = !state.paymentExpanded;
    }

    if (state.paymentExpanded) {
        container.classList.remove('hidden');
        if (chevron) chevron.classList.add('rotate-180');
    } else {
        container.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
    }
}

// ------------------------- BAYAR -------------------------

async function prosesBayar() {
    if (document.getElementById('btn-bayar')?.disabled) return;
    if (state.cart.length === 0) {
        toast('Keranjang masih kosong', true);
        return;
    }
    if (!state.gudangId) {
        toast('Pilih gudang dulu', true);
        return;
    }
    // Validasi alamat pengiriman jika ada item delivery
    const hasDelivery = state.cart.some((i) => i.jenis_pesanan === 'delivery');
    if (hasDelivery && !state.alamatPengiriman.trim()) {
        toast('Alamat pengiriman wajib diisi untuk pesanan Delivery', true);
        return;
    }
    if (state.jenisPembayaran === 'tunai' && state.bayar < totalNeto()) {
        if (!state.paymentExpanded) {
            togglePaymentDetails(true);
        }
        toast('Uang bayar kurang dari total', true);
        setTimeout(() => {
            const inputBayar = document.getElementById('input-bayar');
            if (inputBayar) {
                inputBayar.focus();
                inputBayar.select();
            }
        }, 150);
        return;
    }
    const payload = buildPayload();

    // Diskon nota besar butuh konfirmasi tambahan sebelum simpan.
    if (state.diskonTransaksi > DISKON_BESAR_PERSEN) {
        bukaModalKonfirmasiDiskon(payload);
        return;
    }

    // QRIS & Transfer butuh konfirmasi "dana sudah diterima" sebelum simpan.
    if (state.jenisPembayaran !== 'tunai') {
        bukaModalKonfirmasiNontunai(payload);
        return;
    }

    await simpanTransaksi(payload);
}

function buildPayload() {
    const isPas = state.isUangPas || (state.jenisPembayaran === 'tunai' && state.bayar === totalNeto());
    const nominalBayar = state.jenisPembayaran === 'tunai' ? (isPas ? totalNeto() : state.bayar) : totalNeto();
    const nominalKembalian = state.jenisPembayaran === 'tunai' ? (isPas ? 0 : Math.max(0, nominalBayar - totalNeto())) : 0;

    return {
        gudang_id: state.gudangId,
        tanggal: tanggalHariIni(),
        total: totalKotor(),
        diskon_persen: state.diskonTransaksi,
        diskon: nominalDiskon(),
        neto: totalNeto(),
        subtotal_normal: totalNormal(),
        potongan_barang: totalPotonganBarang(),
        jenis_pembayaran: state.jenisPembayaran,
        bayar: nominalBayar,
        kembalian: nominalKembalian,
        alamat_pengiriman: state.alamatPengiriman || null,
        biaya_kirim: state.biayaKirim || 0,
        details: state.cart.map((i) => ({
            barang_id: i.barang_id,
            nama_barang: i.nama_barang,
            nama_promo: i.nama_promo ?? null,
            gudang_id: state.gudangId,
            satuan: i.satuan,
            jumlah: i.jumlah,
            harga: i.harga,
            harga_asli: i.harga_asli ?? i.harga,
            diskon: i.diskon,
            subtotal: subtotalItem(i),
            is_bonus: !!i.is_bonus,
            promo_id: i.promo_id ?? null,
            jenis_pesanan: i.jenis_pesanan || state.jenisPesanan,
        })),
    };
}

function bukaModalKonfirmasiNontunai(payload) {
    pendingNontunaiPayload = payload;
    const labelMetode = document.getElementById('label-metode-nontunai');
    const labelNominal = document.getElementById('label-nominal-nontunai');
    const labelBayar =
        payload.jenis_pembayaran === 'transfer'
            ? (state.bankTransfer ? `Transfer ${state.bankTransfer}` : 'Transfer')
            : 'QRIS';
    if (labelMetode) labelMetode.textContent = labelBayar;
    if (labelNominal) labelNominal.textContent = rupiah(payload.neto);
    document.getElementById('modal-konfirmasi-nontunai')?.classList.remove('hidden');
    document.getElementById('btn-konfirmasi-nontunai')?.focus();
}

function tutupModalKonfirmasiNontunai() {
    pendingNontunaiPayload = null;
    document.getElementById('modal-konfirmasi-nontunai')?.classList.add('hidden');
}

function bukaModalKonfirmasiDiskon(payload) {
    pendingDiskonPayload = payload;
    const labelPersen = document.getElementById('label-diskon-persen');
    const labelNominal = document.getElementById('label-diskon-nominal');
    if (labelPersen) labelPersen.textContent = `${state.diskonTransaksi}%`;
    if (labelNominal) labelNominal.textContent = rupiah(nominalDiskon());
    document.getElementById('modal-konfirmasi-diskon')?.classList.remove('hidden');
    document.getElementById('btn-konfirmasi-diskon')?.focus();
}

function tutupModalKonfirmasiDiskon() {
    pendingDiskonPayload = null;
    document.getElementById('modal-konfirmasi-diskon')?.classList.add('hidden');
}

/**
 * Setelah konfirmasi diskon besar, lanjut ke alur normal:
 * non-tunai → modal konfirmasi pembayaran, tunai → simpan langsung.
 */
async function lanjutkanTransaksi(payload) {
    if (payload.jenis_pembayaran !== 'tunai') {
        bukaModalKonfirmasiNontunai(payload);
        return;
    }
    await simpanTransaksi(payload);
}

async function simpanTransaksi(payload) {
    const btn = document.getElementById('btn-bayar');
    if (btn) {
        btn.dataset.saving = '1';
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
    }

    try {
        const saved = await simpanPenjualan(payload);
        const savedDetails = Array.isArray(saved.details) ? saved.details : [];

        // Struk & stok lokal pakai detail yang BENAR-BENAR tersimpan di server
        // (server bisa melewati item bonus yang stoknya kurang).
        const detailsStruk = savedDetails.map((d) => {
            const cartMatch = state.cart.find((c) => Number(c.barang_id) === Number(d.barang_id) && !!c.is_bonus === !!d.is_bonus);
            return {
                barang_id: d.barang_id,
                nama_barang: d.barang?.nama_barang
                    ?? state.barang.find((x) => Number(x.id) === Number(d.barang_id))?.nama_barang
                    ?? '-',
                nama_promo: cartMatch?.nama_promo ?? null,
                gudang_id: d.gudang_id,
                satuan: d.satuan,
                jumlah: d.jumlah,
                harga: d.harga,
                harga_asli: d.harga,
                diskon: d.diskon,
                subtotal: d.subtotal,
                is_bonus: !!d.is_bonus,
                promo_id: d.promo_id ?? cartMatch?.promo_id ?? null,
                jenis_pesanan: d.jenis_pesanan ?? cartMatch?.jenis_pesanan ?? state.jenisPesanan,
            };
        });

        tampilkanStruk({
            ...payload,
            ...saved,
            details: detailsStruk,
            nomer_nota: saved.nomer_nota,
            kembalian: saved.kembalian ?? payload.kembalian,
            jam: formatJamWib(saved.created_at),
            bank_transfer: state.bankTransfer,
        });
        for (const d of savedDetails) {
            const b = state.barang.find((x) => Number(x.id) === Number(d.barang_id));
            const units = b ? getUnitsForBarang(b) : [];
            const uObj = units.find((u) => u.satuan === d.satuan);
            const faktor = uObj ? uObj.faktor : 1;
            if (b && b.stok[d.gudang_id] != null) b.stok[d.gudang_id] -= (d.jumlah * faktor);
        }
        resetTransaksi();
    } catch (e) {
        toast(e.message ?? 'Gagal menyimpan transaksi', true);
        renderCart();
    } finally {
        if (btn) {
            delete btn.dataset.saving;
            btn.disabled = false;
            btn.textContent = 'Bayar';
        }
        renderCart();
    }
}

// ------------------------- STRUK -------------------------

function escapeHtml(value) {
    if (value == null) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function highlightMatch(text, q) {
    const query = (q ?? '').trim();
    if (!query) return escapeHtml(text);
    const hay = text.toLowerCase();
    const needle = query.toLowerCase();
    if (!hay.includes(needle)) return escapeHtml(text);
    let result = '';
    let cursor = 0;
    let from = hay.indexOf(needle);
    while (from !== -1) {
        result += escapeHtml(text.slice(cursor, from));
        result += `<mark class="bg-amber-100 text-zinc-900 rounded-[3px] px-0.5">${escapeHtml(text.slice(from, from + needle.length))}</mark>`;
        cursor = from + needle.length;
        from = hay.indexOf(needle, cursor);
    }
    result += escapeHtml(text.slice(cursor));
    return result;
}

function tampilkanStruk(payload) {
    const gudangNama = payload.nama_gudang
        || state.gudang.find((g) => g.id === payload.gudang_id)?.nama_gudang
        || '';

    const diskonPersen = typeof payload.diskon_persen === 'number'
        ? payload.diskon_persen
        : state.diskonTransaksi;

    const subtotalNormal = payload.subtotal_normal
        ?? payload.details.reduce((s, d) => s + Number(d.harga ?? 0) * Number(d.jumlah || 0), 0);
    const potonganBarang = payload.potongan_barang
        ?? payload.details.reduce((s, d) => s + Number(d.diskon || 0), 0);
    const potonganTransaksi = Number(payload.diskon || 0);

    const promoList = state.promoBonus || window.KASIR_DATA?.promoBonus || [];
    const bonusByParent = {};
    const bonusNoParent = [];
    (payload.details || []).forEach((d) => {
        if (!d.is_bonus) return;
        const promo = promoList.find((p) => Number(p.id) === Number(d.promo_id));
        if (promo) {
            const parentId = Number(promo.barang_utama_id);
            if (!bonusByParent[parentId]) bonusByParent[parentId] = [];
            bonusByParent[parentId].push(d);
        } else {
            bonusNoParent.push(d);
        }
    });

    // Group items by jenis_pesanan
    const tipeLabels = {
        dine_in: 'Dine In',
        take_away: 'Take Away',
        delivery: 'Delivery',
    };

    const itemsByTipe = {};
    (payload.details || []).forEach((d) => {
        if (d.is_bonus) return;
        const tipe = d.jenis_pesanan || 'dine_in';
        if (!itemsByTipe[tipe]) itemsByTipe[tipe] = [];
        itemsByTipe[tipe].push(d);
    });
    bonusNoParent.forEach((b) => {
        const tipe = b.jenis_pesanan || 'dine_in';
        if (!itemsByTipe[tipe]) itemsByTipe[tipe] = [];
        itemsByTipe[tipe].push(b);
    });

    const tipeOrder = ['dine_in', 'take_away', 'delivery'];
    const itemsHtml = tipeOrder
        .filter((tipe) => itemsByTipe[tipe] && itemsByTipe[tipe].length > 0)
        .map((tipe) => {
            const info = tipeLabels[tipe];
            const items = itemsByTipe[tipe];
            const headerHtml = `
                <div class="flex items-center gap-1.5 py-1 border-b border-dashed border-zinc-400">
                    <span class="text-xs font-bold uppercase tracking-wide text-zinc-600">${info}</span>
                </div>`;
            const itemsHtml = items
                .map((d, idx) => {
                    const nama = d.nama_barang
                        ?? state.barang.find((b) => b.id === d.barang_id)?.nama_barang
                        ?? '-';
                    const hargaUnit = Number(d.harga_asli ?? d.harga ?? 0);
                    const potonganItem = Number(d.diskon || 0);
                    const potonganItemPersen = persenPotongan(potonganItem, hargaUnit * Number(d.jumlah || 0));
                    const isBonus = !!d.is_bonus;
                    const namaPromo = d.nama_promo ?? null;
                    if (isBonus) {
                        return `
                            <div class="pl-4 border-l-2 border-zinc-400 ml-2 -mt-1">
                                <p class="font-semibold text-xs text-zinc-600 italic leading-snug">${escapeHtml(nama)}</p>
                                <div class="flex justify-between items-start text-xs leading-normal">
                                    <span class="text-zinc-600">${d.jumlah} ${escapeHtml(d.satuan)} <span class="font-bold italic">(GRATIS${namaPromo ? ` • ${escapeHtml(namaPromo)}` : ''})</span></span>
                                    <div class="text-right">
                                        <span class="font-bold text-xs text-zinc-600">${rupiah(d.subtotal)}</span>
                                        <div class="text-[10px] font-bold text-zinc-700 mt-0.5">[BONUS]</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    return `
                        <div class="space-y-0.5">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="font-bold text-sm text-zinc-900 leading-snug">${idx + 1}. ${escapeHtml(nama)}</p>
                                ${potonganItem > 0
                                    ? `<span class="text-xs font-semibold text-red-500 shrink-0">potongan -${potonganItemPersen}%</span>`
                                    : ''}
                            </div>
                            <div class="flex justify-between items-start text-xs leading-normal">
                                <span class="text-zinc-700">${d.jumlah} ${escapeHtml(d.satuan)} x ${rupiah(hargaUnit)}</span>
                                <div class="text-right">
                                    <span class="font-bold text-sm text-zinc-900">${rupiah(d.subtotal)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                })
                .join('<div class="my-2"></div>');
            return headerHtml + itemsHtml;
        })
        .join('<div class="my-3 border-t border-dashed border-zinc-400"></div>');

    const jenisPembayaran = String(payload.jenis_pembayaran || 'tunai').toLowerCase();
    const labelBayar = jenisPembayaran === 'transfer'
        ? (payload.bank_transfer ? `Transfer ${payload.bank_transfer}` : 'Transfer')
        : (jenisPembayaran === 'qris' ? 'QRIS' : 'Tunai');

    const namaKasir = payload.nama_kasir
        || (typeof window !== 'undefined' && window.KASIR_DATA?.karyawan?.nama)
        || 'Kasir';

    const body = document.getElementById('struk-body');
    if (body) {
        const toko = payload.toko
            || (typeof window !== 'undefined' && window.KASIR_DATA?.toko)
            || TOKO_DEFAULT;

        const dateStr = payload.tanggal ? formatTanggalRupiah(payload.tanggal) : '';
        const fullDateTime = `${dateStr || payload.tanggal}${payload.jam ? ` ${escapeHtml(payload.jam)}` : ''}`;

        const alamatHtml = toko.alamat ? `<p class="text-xs text-zinc-700 font-normal mt-0.5">${escapeHtml(toko.alamat)}</p>` : '';
        const kontakHtml = toko.kontak ? `<p class="text-xs text-zinc-700 font-normal mt-0.5">${escapeHtml(toko.kontak)}</p>` : '';
        const biayaKirimVal = Number(payload.biaya_kirim || 0);
        const netoSebelumKirim = Math.max(0, (payload.neto || 0) - biayaKirimVal);

        body.innerHTML = `
            <div class="text-center font-sans">
                <h2 class="font-bold text-base text-zinc-900 tracking-tight">${escapeHtml(toko.nama || 'Toko PKL')}</h2>
                ${alamatHtml}
                ${kontakHtml}
                <p class="text-xs text-zinc-700 font-normal mt-0.5">${escapeHtml(gudangNama)}</p>
                <p class="text-xs text-zinc-700 font-normal mt-0.5">Nota: ${escapeHtml(payload.nomer_nota)} &bull; ${escapeHtml(fullDateTime)}</p>
                <p class="text-xs text-zinc-700 font-normal mt-0.5">Kasir: ${escapeHtml(namaKasir)}</p>
            </div>

            <div class="border-b border-dashed border-zinc-400 my-3"></div>

            <div class="space-y-3 font-sans">
                ${itemsHtml}
            </div>

            <div class="border-b border-dashed border-zinc-400 my-3"></div>

            <div class="space-y-1.5 text-xs text-zinc-800 font-sans">
                <div class="flex justify-between items-center">
                    <span>Subtotal</span>
                    <span class="text-zinc-900">${rupiah(subtotalNormal)}</span>
                </div>

                ${potonganBarang > 0 ? `
                    <div class="flex justify-between items-center text-xs font-normal text-zinc-800">
                        <span>Potongan Barang (Total Rp):</span>
                        <span class="text-zinc-900">- ${rupiah(potonganBarang)}</span>
                    </div>
                ` : ''}

                ${potonganTransaksi > 0 ? `
                    <div class="flex justify-between items-center text-xs font-normal text-zinc-800">
                        <span>Diskon Nota ${diskonPersen > 0 ? `(${diskonPersen}%)` : ''}:</span>
                        <span class="text-zinc-900">- ${rupiah(potonganTransaksi)}</span>
                    </div>
                ` : ''}

                <div class="flex justify-between items-center font-semibold text-zinc-900">
                    <span>Neto</span>
                    <span>${rupiah(netoSebelumKirim)}</span>
                </div>

                ${biayaKirimVal > 0 ? `
                <div class="flex justify-between items-center text-xs font-normal text-zinc-800">
                    <span>Biaya Kirim</span>
                    <span class="text-zinc-900">${rupiah(biayaKirimVal)}</span>
                </div>
                ` : ''}
            </div>

            <div class="border-b border-dashed border-zinc-400 my-3"></div>

            <div class="space-y-1.5 text-xs font-sans">
                <div class="flex justify-between items-baseline text-zinc-900 font-bold text-base">
                    <span>Total</span>
                    <span>${rupiah(payload.neto)}</span>
                </div>
                <div class="flex justify-between items-center text-zinc-800 mt-2">
                    <span>Bayar (${escapeHtml(labelBayar)})</span>
                    <span class="text-zinc-900">${rupiah(payload.bayar)}</span>
                </div>
                <div class="flex justify-between items-center text-zinc-800 mt-2">
                    <span>Kembalian</span>
                    <span class="font-bold text-sm text-zinc-900">${rupiah(payload.kembalian)}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-zinc-400 my-4"></div>

            <p class="text-center text-xs text-zinc-500 font-normal my-4">Terima kasih atas kunjungan Anda</p>
        `;
    }
    document.getElementById('modal-struk')?.classList.remove('hidden');
    document.getElementById('btn-print-struk')?.focus();
}

// ------------------------- RIWAYAT TRANSAKSI -------------------------

function formatTanggalRupiah(tgl) {
    if (!tgl) return '';
    const [tahun, bulan, hari] = tgl.split('-');
    if (!tahun || !bulan || !hari) return tgl;
    return `${hari}-${bulan}-${tahun}`;
}

function tanggalHariIni() {
    return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Jakarta' }).format(new Date());
}

function formatJamWib(value) {
    const d = value ? new Date(value) : new Date();
    if (Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(d);
}

function updateJamHeader() {
    const elWaktu = document.getElementById('jam-header-time');
    const elTanggal = document.getElementById('jam-header-date');
    if (!elWaktu && !elTanggal) return;
    const now = new Date();
    const waktu = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(now);
    const tanggal = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(now);
    if (elWaktu) elWaktu.textContent = waktu;
    if (elTanggal) elTanggal.textContent = tanggal;
}

let riwayatTanggalAktif = null;
let riwayatReqId = 0;
const RIWAYAT_BATCH = 50;
const AUTO_REFRESH_MS = 60000;
let riwayatCache = [];
let riwayatTerakhirId = null;
let riwayatHasMore = false;
let riwayatSummary = null;
let riwayatFilter = { kasir: '', gudang_id: '', metode: '' };
let pendingCetakPenjualanId = null;

function renderRiwayatItems(items, append) {
    const list = document.getElementById('riwayat-list');
    if (!list) return;
    if (append) {
        list.innerHTML += items
            .map((r) => templateRiwayatItem(r))
            .join('');
    } else {
        list.innerHTML = items.map((r) => templateRiwayatItem(r)).join('');
    }
}

function templateRiwayatItem(r) {
    const labelBayar = r.jenis_pembayaran === 'transfer'
        ? 'Transfer'
        : r.jenis_pembayaran?.toUpperCase() ?? '-';
    return `<div class="flex items-center justify-between gap-3 py-3 px-2">
        <div class="min-w-0">
            <p class="text-sm font-bold text-zinc-900 truncate">${escapeHtml(r.nomer_nota)}</p>
            <p class="text-[11px] text-zinc-500 mt-0.5">
                ${escapeHtml(r.jam)} · ${escapeHtml(r.nama_kasir)} · ${r.jumlah_item} item · ${escapeHtml(r.gudang)}
            </p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <div class="text-right">
                <p class="text-sm font-black tabular-nums">${rupiah(r.neto)}</p>
                <p class="text-[10px] font-bold ${r.jenis_pembayaran === 'tunai' ? 'text-emerald-600' : 'text-sky-600'}">${labelBayar}</p>
            </div>
            <button type="button" data-cetak-ulang="${r.id}"
                class="shrink-0 px-3 py-1.5 rounded-lg border border-zinc-200 text-xs font-bold text-zinc-700 hover:border-zinc-900 hover:bg-zinc-50 transition-colors cursor-pointer">Cetak</button>
        </div>
    </div>`;
}

function setRiwayatLebih(visible) {
    const btn = document.getElementById('btn-riwayat-lebih');
    if (btn) {
        btn.classList.toggle('hidden', !visible);
        btn.disabled = false;
        btn.textContent = 'Muat lebih banyak';
    }
}

function setRiwayatLebihLoading() {
    const btn = document.getElementById('btn-riwayat-lebih');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Memuat…';
    }
}

function renderRiwayatSummary(summary) {
    riwayatSummary = summary ?? { jumlah: 0, total_neto: 0 };
    const el = document.getElementById('riwayat-summary');
    if (!el) return;
    const jumlah = riwayatSummary.jumlah ?? 0;
    const total = riwayatSummary.total_neto ?? 0;
    const lbl = document.getElementById('riwayat-summary-jumlah');
    const totalEl = document.getElementById('riwayat-summary-total');
    if (lbl) lbl.textContent = jumlah === 0 ? 'Belum ada transaksi' : `${jumlah} transaksi`;
    if (totalEl) totalEl.textContent = rupiah(total);
    el.classList.toggle('hidden', jumlah === 0);
}

async function muatRiwayat(tanggal, append = false) {
    const reqId = ++riwayatReqId;
    riwayatTanggalAktif = tanggal;
    const pemuatan = document.getElementById('riwayat-pemuatan');
    const list = document.getElementById('riwayat-list');
    const kosong = document.getElementById('riwayat-kosong');
    const label = document.getElementById('riwayat-tanggal-label');

    if (!append) {
        riwayatTerakhirId = null;
        riwayatHasMore = false;
        riwayatCache = [];
        list?.classList.add('hidden');
        kosong?.classList.add('hidden');
        setRiwayatLebih(false);
        pemuatan?.classList.remove('hidden');
    } else {
        setRiwayatLebihLoading();
    }

    const labelTeks = tanggal
        ? `Transaksi ${formatTanggalRupiah(tanggal)}`
        : 'Transaksi hari ini';
    if (label) label.textContent = labelTeks;

    try {
        const res = await getRiwayat(tanggal, {
            limit: RIWAYAT_BATCH,
            before: append ? riwayatTerakhirId : undefined,
            kasir: riwayatFilter.kasir || undefined,
            gudang_id: riwayatFilter.gudang_id || undefined,
            metode: riwayatFilter.metode || undefined,
        });
        if (reqId !== riwayatReqId) return;
        pemuatan?.classList.add('hidden');

        const items = Array.isArray(res) ? res : (res?.items ?? []);
        renderRiwayatSummary(Array.isArray(res) ? undefined : res?.summary);

        if (items.length === 0 && !append) {
            list?.classList.add('hidden');
            kosong?.classList.remove('hidden');
            setRiwayatLebih(false);
            return;
        }

        riwayatCache = append ? riwayatCache.concat(items) : items;
        if (items.length > 0) riwayatTerakhirId = items[items.length - 1].id;
        riwayatHasMore = items.length === RIWAYAT_BATCH;
        renderRiwayatItems(items, append);

        list?.classList.remove('hidden');
        kosong?.classList.add('hidden');
        setRiwayatLebih(riwayatHasMore);
    } catch (e) {
        if (reqId !== riwayatReqId) return;
        pemuatan?.classList.add('hidden');
        pemuatan.textContent = e.message ?? 'Gagal memuat riwayat';
        pemuatan.classList.remove('hidden');
        setRiwayatLebih(true);
    }
}

function bukaModalRiwayat() {
    const modal = document.getElementById('modal-riwayat');
    if (!modal) return;
    modal.classList.remove('hidden');

    const inputTanggal = document.getElementById('riwayat-tanggal');
    if (inputTanggal) inputTanggal.value = tanggalHariIni();
    muatRiwayat(inputTanggal?.value || tanggalHariIni());
}

function tutupModalRiwayat() {
    document.getElementById('modal-riwayat')?.classList.add('hidden');
}

async function muatUlangData() {
    try {
        const [barang, jenis, gudang] = await Promise.all([
            getBarang(),
            getJenisBarang(),
            getGudang(),
        ]);
        state.barang = barang;
        state.jenisBarang = jenis;
        state.gudang = gudang;
        if (!state.gudangId || !gudang.some((g) => g.id === state.gudangId)) {
            state.gudangId = gudang[0]?.id ?? null;
            if (gudangSetValue) gudangSetValue(state.gudangId);
        }
        render();
        toast('Data produk & stok diperbarui');
    } catch (err) {
        toast(err.message ?? 'Gagal memuat ulang data', true);
    }
}

async function refreshStokSilent() {
    if (!document.hasFocus()) return;
    const ae = document.activeElement;
    if (ae && ['INPUT', 'TEXTAREA', 'SELECT'].includes(ae.tagName)) return;
    if (ae && (ae.hasAttribute?.('data-add') || ae.closest?.('[data-add]'))) return;
    const modalTerbuka = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-nontunai', 'modal-konfirmasi-diskon', 'modal-panduan-shortcut', 'modal-riwayat', 'modal-password-cetak'].some((id) => {
        const el = document.getElementById(id);
        return el && !el.classList.contains('hidden');
    });
    if (modalTerbuka) return;
    try {
        const [barang] = await Promise.all([getBarang()]);
        state.barang = barang;
        renderProduk();
    } catch (_) {
    }
}

function mintaPasswordCetak(penjualanId) {
    const r = riwayatCache.find((x) => String(x.id) === String(penjualanId));
    if (!r) {
        toast('Nota tidak ditemukan', true);
        return;
    }
    pendingCetakPenjualanId = penjualanId;
    const label = document.getElementById('label-nota-password');
    if (label) label.textContent = r.nomer_nota;

    const input = document.getElementById('input-password-cetak');
    if (input) input.value = '';

    const errEl = document.getElementById('error-password-cetak');
    if (errEl) {
        errEl.textContent = '';
        errEl.classList.add('hidden');
    }

    const modal = document.getElementById('modal-password-cetak');
    if (modal) modal.classList.remove('hidden');
    setTimeout(() => input?.focus(), 50);
}

function tutupModalPasswordCetak() {
    pendingCetakPenjualanId = null;
    const modal = document.getElementById('modal-password-cetak');
    if (modal) modal.classList.add('hidden');
    const input = document.getElementById('input-password-cetak');
    if (input) input.value = '';
}

async function verifikasiPasswordCetak(e) {
    if (e) e.preventDefault();
    const input = document.getElementById('input-password-cetak');
    const pw = input ? input.value.trim() : '';
    const errEl = document.getElementById('error-password-cetak');
    const idToPrint = pendingCetakPenjualanId;

    if (!idToPrint) {
        tutupModalPasswordCetak();
        return;
    }

    try {
        const data = await verifikasiCetakUlang(idToPrint, pw);
        tutupModalPasswordCetak();
        cetakUlangRiwayat(data);
    } catch (err) {
        if (errEl) {
            errEl.textContent = err.message ?? 'Password salah! Silakan coba lagi.';
            errEl.classList.remove('hidden');
        }
        if (input) {
            input.focus();
            input.select();
        }
    }
}

function cetakUlangRiwayat(r) {
    if (!r) {
        toast('Nota tidak ditemukan', true);
        return;
    }
    tampilkanStruk({
        gudang_id: r.gudang_id ?? state.gudangId,
        nama_gudang: r.gudang,
        tanggal: r.tanggal,
        total: r.total,
        diskon: r.diskon,
        diskon_persen: r.diskon_persen ?? (
            r.diskon > 0 && r.neto > 0
                ? Math.round((Number(r.diskon) / (Number(r.diskon) + Number(r.neto))) * 100)
                : 0
        ),
        neto: r.neto,
        biaya_kirim: r.biaya_kirim ?? 0,
        subtotal_normal: r.details.reduce((s, d) => s + Number(d.harga || 0) * Number(d.jumlah || 0), 0),
        potongan_barang: r.details.reduce((s, d) => s + Number(d.diskon || 0), 0),
        jenis_pembayaran: r.jenis_pembayaran,
        bayar: r.bayar,
        kembalian: r.kembalian,
        nomer_nota: r.nomer_nota,
        nama_kasir: r.nama_kasir,
        jam: r.jam,
        details: r.details.map((d) => ({
            barang_id: d.barang_id,
            nama_barang: d.nama_barang,
            jumlah: d.jumlah,
            satuan: d.satuan,
            harga: d.harga,
            diskon: d.diskon,
            subtotal: d.subtotal,
            is_bonus: !!d.is_bonus,
            promo_id: d.promo_id ?? null,
            jenis_pesanan: d.jenis_pesanan ?? 'dine_in',
        })),
    });
    tutupModalRiwayat();
}

// ------------------------- RENDER -------------------------

const TILE_TINTS = [
    'bg-zinc-100 text-zinc-500',
    'bg-stone-100 text-stone-500',
    'bg-zinc-200/70 text-zinc-600',
    'bg-stone-200/70 text-stone-600',
];
function tileTint(nama) {
    let h = 0;
    for (const c of nama) h = (h * 31 + c.charCodeAt(0)) % 997;
    return TILE_TINTS[h % TILE_TINTS.length];
}
function inisial(nama) {
    const kata = nama.trim().split(/\s+/);
    return ((kata[0]?.[0] ?? '') + (kata[1]?.[0] ?? '')).toUpperCase();
}

let lastProdukKey = null;
let highlightedIdx = -1;
let skipClick = false;
let gudangSetValue = null;
let cartIdx = -1;
let panduanPrevFocus = null;
let allowUnload = false;
let prevCartCount = 0;
const bonusToastShown = new Set();

function barangTampil() {
    const q = state.search.toLowerCase();
    return state.barang.filter((b) => {
        if (state.filterJenis && b.jenis_barang_id !== state.filterJenis) return false;
        if (q) {
            const namaMatch = b.nama_barang.toLowerCase().includes(q);
            const kodeMatch = inisial(b.nama_barang).toLowerCase() === q;
            const barcodeMatch = String(b.barcode ?? '').toLowerCase().includes(q);
            const nomerSeriMatch = String(b.nomer_seri ?? '').toLowerCase().includes(q);
            if (!namaMatch && !kodeMatch && !barcodeMatch && !nomerSeriMatch) return false;
        }
        return true;
    });
}

function getJmlKolom() {
    const grid = document.getElementById('grid-produk');
    return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 3;
}

function focusSorot() {
    const grid = document.getElementById('grid-produk');
    if (!grid) return;
    const btns = grid.querySelectorAll('[data-add]');
    if (btns[highlightedIdx]) {
        btns[highlightedIdx].focus();
        btns[highlightedIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function renderProduk() {
    const grid = document.getElementById('grid-produk');
    if (!grid) return;

    const q = state.search.toLowerCase();
    const list = barangTampil();

    const key = `${state.gudangId}|${state.filterJenis}|${q}`;
    const animate = key !== lastProdukKey;
    lastProdukKey = key;

    if (list.length === 0) {
        grid.innerHTML = `<div class="col-span-full text-center py-20">
            <p class="text-sm font-semibold text-zinc-600">Barang tidak ditemukan</p>
            <p class="text-xs text-zinc-500 mt-1">Coba kata kunci atau kategori lain</p>
        </div>`;
        return;
    }

    const cartSelId = (cartIdx >= 0 && cartIdx < state.cart.length) ? state.cart[cartIdx].barang_id : null;

    grid.innerHTML = list
        .map((b, idx) => {
            const stok = stokTersedia(b);
            const isHabisStatus = b.status === 'habis';
            const habis = isHabisStatus || stok <= 0;
            const menipis = !habis && stok <= 5;
            const sorot = highlightedIdx === idx ? 'ring-2 ring-zinc-900 shadow-lg shadow-zinc-900/15' : '';
            const sorotCart = cartSelId === b.id;
            const kelasRing = sorot || (sorotCart ? 'ring-2 ring-zinc-900' : '');
            const units = getUnitsForBarang(b);
            const defaultUnit = units[0]?.satuan ?? b.satuan ?? 'Pcs';
            const jumlahDiKeranjang = state.cart.filter((i) => i.barang_id === b.id).reduce((s, i) => s + i.jumlah, 0);
            const diKeranjang = jumlahDiKeranjang > 0;

            const hasTier = (b.min_qty_2 && Number(b.nilai_tier_2) > 0) || (b.min_qty_3 && Number(b.nilai_tier_3) > 0) || (b.min_qty_1 && Number(b.nilai_tier_1) > 0);
            const tierBadgeProdukHtml = hasTier
                ? `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-zinc-800 bg-zinc-100 border border-zinc-200 px-1.5 py-0.5 rounded-md mt-1"><svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1 -2.83 0l-9.17 -9.17a2 2 0 0 1 -0.59 -1.42v-5.99a2 2 0 0 1 2 -2h5.99a2 2 0 0 1 1.42 0.59l9.17 9.17a2 2 0 0 1 0 2.83z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>Promo Qty</span>`
                : '';

            const fotoHtml = b.gambar
                ? `<img src="${b.gambar}" alt="${escapeHtml(b.nama_barang)}" class="max-h-28 max-w-full object-contain drop-shadow-xs group-hover:scale-105 transition-transform duration-300" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-14 h-14 rounded-2xl ${tileTint(b.nama_barang)} flex items-center justify-center text-base font-black text-zinc-700 select-none shadow-2xs\\'>${inisial(b.nama_barang)}</div>';" />`
                : `<div class="w-14 h-14 rounded-2xl ${tileTint(b.nama_barang)} flex items-center justify-center text-base font-black text-zinc-700 select-none shadow-2xs">
                    ${inisial(b.nama_barang)}
                </div>`;

            return `<button data-add="${b.id}" ${habis ? 'disabled' : ''} style="--i: ${Math.min(idx, 16)}"
                class="${animate ? 'anim-fade-up ' : ''}relative group text-left bg-white rounded-2xl border p-3.5 flex flex-col justify-between transition-all duration-200
                       ${diKeranjang ? 'border-zinc-950 ring-2 ring-zinc-950 shadow-sm' : 'border-zinc-200/90 shadow-2xs hover:border-zinc-400 hover:shadow-md'}
                       ${habis
                    ? 'opacity-50 cursor-not-allowed'
                    : 'cursor-pointer hover:-translate-y-1 active:translate-y-0 active:scale-[0.98]'}
                       ${kelasRing}">
                
                <!-- Wadah gambar berlatar abu-abu halus dan sudut rounded -->
                <div class="relative w-full h-36 rounded-xl bg-zinc-100/70 border border-zinc-200/50 flex items-center justify-center p-3 overflow-hidden">
                    <!-- Indikator kotak kiri atas -->
                    ${diKeranjang
                        ? `<div class="absolute top-2.5 left-2.5 z-10 w-5 h-5 rounded-md bg-black text-white flex items-center justify-center text-[10px] font-bold shadow-xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                           </div>`
                        : `<div class="absolute top-2.5 left-2.5 z-10 w-5 h-5 rounded-md border border-zinc-300/80 bg-white/90 flex items-center justify-center group-hover:border-zinc-700 transition-colors"></div>`
                    }

                    <!-- Badge jumlah item jika ada di keranjang -->
                    ${jumlahDiKeranjang > 0
                        ? `<span class="absolute top-2.5 right-2.5 z-10 px-2 py-0.5 rounded-lg bg-black text-white text-[11px] font-bold shadow-xs tabular-nums">×${jumlahDiKeranjang}</span>`
                        : ''
                    }

                    ${fotoHtml}

                    ${habis
                        ? `<div class="absolute inset-0 bg-white/85 backdrop-blur-[1px] flex items-center justify-center z-10">
                            <span class="px-2.5 py-1 rounded-full bg-red-100 text-red-600 text-xs font-bold border border-red-200 shadow-2xs">Stok Habis</span>
                           </div>`
                        : ''
                    }
                </div>

                <!-- Informasi Barang -->
                <div class="mt-3 flex flex-col gap-2 flex-1 justify-between w-full">
                    <div>
                        <p class="font-bold text-zinc-900 text-sm md:text-base leading-snug line-clamp-1 group-hover:text-black transition-colors" title="${escapeHtml(b.nama_barang)}">
                            ${highlightMatch(b.nama_barang, state.search)}
                        </p>
                        ${tierBadgeProdukHtml}
                    </div>

                    <!-- Baris Harga (Hitam) & Stok (Menggantikan Bintang) -->
                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-zinc-100">
                        <div class="flex items-baseline gap-1">
                            <span class="text-base sm:text-lg font-black text-black tracking-tight tabular-nums">${rupiah(b.harga_jual)}</span>
                            <span class="text-[11px] font-medium text-zinc-400">/${defaultUnit}</span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 ${habis ? 'text-red-500' : menipis ? 'text-amber-600' : 'text-zinc-500'}">
                            ${habis
                                ? `<span class="text-[11px] font-bold">Stok 0</span>`
                                : `<span class="text-[11px] font-medium text-zinc-400">Stok:</span><span class="text-xs font-bold text-zinc-800 tabular-nums">${stok}</span>`
                            }
                        </div>
                    </div>
                </div>
            </button>`;
        })
        .join('');
}

function applyPromoBonusRules() {
    const promoList = state.promoBonus || window.KASIR_DATA?.promoBonus || [];
    if (!promoList || promoList.length === 0) return;

    // Filter out previous auto-generated bonus items
    state.cart = state.cart.filter((item) => !item.is_bonus);

    const bonusToAdd = [];

    promoList.forEach((promo) => {
        const barangMain = state.barang.find((b) => Number(b.id) === Number(promo.barang_utama_id));
        if (!barangMain) return;
        const units = getUnitsForBarang(barangMain);

        const mainItems = state.cart.filter(
            (item) => Number(item.barang_id) === Number(promo.barang_utama_id) && !item.is_bonus
        );

        if (!mainItems || mainItems.length === 0) return;

        const unitUtamaObj = units.find((u) => u.satuan === promo.satuan_utama);
        const faktorUtama = unitUtamaObj ? Number(unitUtamaObj.faktor || 1) : 1;
        const minQtyInBase = Number(promo.min_qty_utama || 1) * faktorUtama;
        const qtyBonusPerBagi = Number(promo.qty_bonus || 1);

        // Kumpulkan jumlah dasar item utama per tipe pesanan.
        const qtyPerTipe = {};
        mainItems.forEach((item) => {
            const unitObj = units.find((u) => u.satuan === item.satuan);
            const faktor = unitObj ? Number(unitObj.faktor || 1) : 1;
            const tipe = item.jenis_pesanan || state.jenisPesanan;
            qtyPerTipe[tipe] = (qtyPerTipe[tipe] || 0) + Number(item.jumlah || 0) * faktor;
        });

        const totalMainQty = Object.values(qtyPerTipe).reduce((s, q) => s + q, 0);
        if (totalMainQty < minQtyInBase) return;

        let multiplier = 1;
        if (promo.is_kelipatan) {
            multiplier = Math.floor(totalMainQty / minQtyInBase);
        }

        const totalBonusQty = qtyBonusPerBagi * multiplier;
        if (totalBonusQty < 1) return;

        const barangBonus = state.barang.find((b) => Number(b.id) === Number(promo.barang_bonus_id));
        if (!barangBonus) return;

        const defaultBonusUnit = promo.satuan_bonus || barangBonus.satuan || 'Pcs';

        const stokTersediaBonus = stokTersedia(barangBonus);
        if (stokTersediaBonus <= 0) {
            const keyToast = `${promo.id}-${barangBonus.id}`;
            if (!bonusToastShown.has(keyToast)) {
                bonusToastShown.add(keyToast);
                toast(`Stok barang bonus ${barangBonus.nama_barang} di gudang ini habis`, true);
            }
            return;
        }
        bonusToastShown.delete(`${promo.id}-${barangBonus.id}`);

        // Alokasikan jatah bonus ke tiap tipe sesuai porsi jumlah dasarnya,
        // agar bonus tampil di grup tipe yang benar dan konsisten dengan server.
        let sisaBonus = totalBonusQty;
        Object.entries(qtyPerTipe)
            .filter(([, q]) => q > 0)
            .sort((a, b) => b[1] - a[1])
            .forEach(([tipe, qty], idx, arr) => {
                const isTerakhir = idx === arr.length - 1;
                let jatah;
                if (isTerakhir) {
                    jatah = sisaBonus;
                } else {
                    jatah = Math.floor((qty / totalMainQty) * totalBonusQty);
                }
                if (jatah < 1) return;
                sisaBonus -= jatah;

                bonusToAdd.push({
                    barang_id: barangBonus.id,
                    nama_barang: barangBonus.nama_barang,
                    nama_promo: promo.nama_promo,
                    key: `${cartKey(barangBonus.id, tipe)}:bonus:${promo.id}:${tipe}`,
                    satuan: defaultBonusUnit,
                    harga: 0,
                    harga_asli: 0,
                    jumlah: jatah,
                    diskon: 0,
                    is_bonus: true,
                    promo_id: promo.id,
                    jenis_pesanan: tipe,
                });
            });
    });

    bonusToAdd.forEach((bonus) => {
        state.cart.push(bonus);
    });
}

function updateCartTierPrices() {
    state.cart.forEach((i) => {
        if (i.is_bonus) {
            i.harga = 0;
            i.harga_asli = 0;
            i.diskon = 0;
            return;
        }

        const barang = state.barang.find((b) => Number(b.id) === Number(i.barang_id));
        if (!barang) return;

        const units = getUnitsForBarang(barang);
        const unitObj = units.find((u) => u.satuan === i.satuan) ?? units[0];
        const basePrice = unitObj ? unitObj.harga_jual : Number(barang.harga_jual || 0);

        i.harga_asli = basePrice;

        const faktor = unitObj ? Number(unitObj.faktor || 1) : 1;
        const totalQtyDasar = Number(i.jumlah || 0) * faktor;

        const tipe = barang.tipe_harga_bertingkat || 'persen';

        const min1 = barang.min_qty_1 !== null && barang.min_qty_1 !== undefined ? Number(barang.min_qty_1) : 0;
        const val1 = Number(barang.nilai_tier_1 || 0);

        const min2 = barang.min_qty_2 !== null && barang.min_qty_2 !== undefined ? Number(barang.min_qty_2) : 0;
        const val2 = Number(barang.nilai_tier_2 || 0);

        const min3 = barang.min_qty_3 !== null && barang.min_qty_3 !== undefined ? Number(barang.min_qty_3) : 0;
        const val3 = Number(barang.nilai_tier_3 || 0);

        const tiers = [];
        if (min3 > 0 && val3 > 0) tiers.push({ min_qty: min3, nilai: val3 });
        if (min2 > 0 && val2 > 0) tiers.push({ min_qty: min2, nilai: val2 });
        if (min1 > 0 && val1 > 0) tiers.push({ min_qty: min1, nilai: val1 });

        tiers.sort((a, b) => b.min_qty - a.min_qty);

        const matched = tiers.find((t) => totalQtyDasar >= t.min_qty);
        const matchedNilai = matched ? matched.nilai : null;

        if (matchedNilai !== null && matchedNilai > 0) {
            if (tipe === 'persen') {
                const discounted = basePrice * (1 - matchedNilai / 100);
                i.harga = Math.max(0, Math.round(discounted));
            } else if (tipe === 'nominal') {
                i.harga = Math.max(0, Math.round(matchedNilai * faktor));
            }
            // potongan rupiah dari harga bertingkat untuk item ini
            i.diskon = Math.max(0, Math.round((basePrice - i.harga) * Number(i.jumlah || 0)));
        } else {
            i.harga = basePrice;
            i.diskon = 0;
        }
    });

    applyPromoBonusRules();
}

function buildCartCard(i, idx) {
    if (i.is_bonus) {
        return `<div data-cart-row="${idx}" tabindex="-1" class="relative bg-white border border-zinc-200/80 rounded-xl p-2.5 shadow-2xs space-y-1.5 transition-colors duration-150">
            <div class="flex items-center gap-2.5">
                <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-zinc-100/90 border border-zinc-200 flex items-center justify-center text-[13px] font-black select-none text-zinc-700 shadow-2xs">
                    BNS
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-zinc-900 bg-zinc-200 border border-zinc-300 px-1.5 py-0.5 rounded-md shrink-0">[BONUS]</span>
                        <p class="text-[13px] font-bold text-zinc-900 leading-snug truncate" title="${escapeHtml(i.nama_barang)}">${escapeHtml(i.nama_barang)}</p>
                        ${tipeBadgeHtml(i.jenis_pesanan)}
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[13px] font-bold text-emerald-500 tabular-nums">Rp 0</span>
                        <span class="text-xs font-medium text-zinc-400 tabular-nums">x${i.jumlah} (GRATIS)</span>
                    </div>
                </div>
            </div>
        </div>`;
    }

    const barang = state.barang.find((b) => Number(b.id) === Number(i.barang_id));
    const units = barang ? getUnitsForBarang(barang) : [{ satuan: i.satuan, harga_jual: i.harga }];
    const selectedUnitObj = units.find((u) => u.satuan === i.satuan) ?? units[0];

    const customUnitOptionsHtml = units
        .map((u) => {
            const active = u.satuan === i.satuan;
            const checkIcon = `<svg class="w-3.5 h-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5L13 5"/></svg>`;
            return `<button type="button" data-custom-unit-select="${i.key}" data-unit-val="${u.satuan}"
                class="w-full flex items-center justify-between gap-2 text-left text-xs rounded-lg px-2.5 py-1.5 transition-all cursor-pointer ${active
                    ? 'bg-zinc-900 text-white font-bold shadow-xs'
                    : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 font-semibold'
                }">
                <span class="truncate">${u.satuan} <span class="${active ? 'text-zinc-300 font-normal' : 'text-zinc-500 font-normal'}">(${rupiah(u.harga_jual)})</span></span>
                ${active ? checkIcon : ''}
            </button>`;
        })
        .join('');

    const currentHarga = i.harga ?? selectedUnitObj.harga_jual;
    const fotoUrl = barang?.foto || barang?.gambar || null;
    const thumbHtml = fotoUrl
        ? `<img src="${fotoUrl}" alt="${escapeHtml(i.nama_barang)}" class="w-full h-full object-contain drop-shadow-2xs" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\\'text-sm font-black text-zinc-600\\'>${inisial(i.nama_barang)}</span>';">`
        : `<span class="text-sm font-black text-zinc-600">${inisial(i.nama_barang)}</span>`;

    return `<div data-cart-row="${idx}" tabindex="-1" class="relative bg-white border border-zinc-200/80 hover:border-zinc-300 rounded-xl p-2.5 shadow-2xs space-y-1.5 transition-all duration-150 ${cartIdx === idx ? 'ring-2 ring-black' : ''}">
        <div class="flex items-center gap-2.5">
            <!-- Foto Makanan / Produk Persegi Besar -->
            <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-zinc-100/80 border border-zinc-200/70 p-1 flex items-center justify-center select-none shadow-2xs">
                ${thumbHtml}
            </div>

            <!-- Info & Kontrol -->
            <div class="min-w-0 flex-1">
                <!-- Judul Item -->
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[13px] font-bold text-zinc-900 leading-snug truncate" title="${escapeHtml(i.nama_barang)}">${escapeHtml(i.nama_barang)}</p>
                    ${tipeBadgeHtml(i.jenis_pesanan)}
                </div>

                <!-- Harga Hijau & Pengali / Satuan -->
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-[13px] font-bold text-emerald-500 tabular-nums">${rupiah(currentHarga)}</span>
                    <span class="text-xs font-medium text-zinc-400 tabular-nums">x${i.jumlah}</span>
                    
                    ${units.length > 1
                        ? `<div class="relative ml-0.5" data-unit-dropdown-wrapper="${i.key}">
                            <button type="button" data-unit-dropdown-btn="${i.key}"
                                class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-zinc-500 hover:text-zinc-800 bg-zinc-100 hover:bg-zinc-200/70 px-1.5 py-0.5 rounded transition-colors cursor-pointer group/unit">
                                <span>${selectedUnitObj.satuan}</span>
                                <svg data-unit-dropdown-chevron="${i.key}" class="w-2.5 h-2.5 text-zinc-400 group-hover/unit:text-zinc-700 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4"/>
                                </svg>
                            </button>
                            <div data-unit-dropdown-menu="${i.key}"
                                class="hidden anim-scale-in absolute left-0 top-full mt-1.5 min-w-[185px] w-max max-w-[240px] max-h-52 overflow-y-auto z-40 bg-white border border-zinc-200 rounded-xl shadow-xl shadow-zinc-950/10 p-1 space-y-0.5">
                                ${customUnitOptionsHtml}
                            </div>
                        </div>`
                        : `<span class="text-[11px] text-zinc-400 font-normal">/ ${selectedUnitObj.satuan}</span>`
                    }
                </div>

                <!-- Baris Tombol Kontrol: Minus - Qty - Plus (Hitam Kotak/Persegi) & Hapus (Tempat Sampah Kanan) -->
                <div class="flex items-center justify-between gap-2 mt-1.5">
                    <div class="flex items-center gap-2">
                        <button data-minus="${i.key}" type="button" 
                            class="w-5 h-5 rounded-md hover:bg-zinc-100 text-zinc-800 font-bold transition flex items-center justify-center cursor-pointer text-sm select-none leading-none" 
                            title="Kurangi">−</button>
                        <input data-qty="${i.key}" type="number" min="1" value="${i.jumlah}"
                            class="w-5 text-center text-[11px] font-bold text-zinc-900 tabular-nums bg-transparent focus:outline-none focus:bg-zinc-100 rounded py-0.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                        <button data-plus="${i.key}" type="button" 
                            class="w-5 h-5 rounded-md bg-black hover:bg-zinc-800 text-white font-bold transition flex items-center justify-center cursor-pointer text-xs shadow-xs select-none" 
                            title="Tambah">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </button>
                    </div>

                    <button data-del="${i.key}" type="button" class="text-zinc-400 hover:text-red-500 transition-colors p-1 cursor-pointer rounded-lg hover:bg-zinc-100" title="Hapus item">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        ${Number(i.diskon || 0) > 0
            ? `<div class="flex items-center justify-between pt-1 border-t border-zinc-100 text-[11px]">
                <span class="text-zinc-400 font-medium">Potongan barang</span>
                <span class="text-zinc-700 font-semibold tabular-nums">−${rupiah(i.diskon * i.jumlah)} (${persenPotongan(i.diskon, (i.harga_asli ?? i.harga) * i.jumlah)}%)</span>
            </div>`
            : ''}
    </div>`;
}

const TIPE_ICON_SVG = {
    dine_in: '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
    take_away: '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    delivery: '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>',
};

const TIPE_LABEL = { dine_in: 'Dine In', take_away: 'Take Away', delivery: 'Delivery' };

const TIPE_BADGE = {
    dine_in: { cls: 'bg-zinc-100 text-zinc-600 border-zinc-200', label: 'Dine In' },
    take_away: { cls: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Take Away' },
    delivery: { cls: 'bg-sky-50 text-sky-700 border-sky-200', label: 'Delivery' },
};

function tipeBadgeHtml(jenis) {
    const tipe = TIPE_BADGE[jenis] ? jenis : 'dine_in';
    const b = TIPE_BADGE[tipe] || TIPE_BADGE.dine_in;
    return `<span class="inline-flex items-center gap-1 text-[10px] font-bold ${b.cls} border px-1.5 py-0.5 rounded-md shrink-0">
                ${TIPE_ICON_SVG[tipe] || ''} ${b.label}
            </span>`;
}

function renderCart() {
    const wrap = document.getElementById('cart-items');
    if (!wrap) return;

    if (cartIdx >= state.cart.length) cartIdx = state.cart.length - 1;
    if (state.cart.length === 0) cartIdx = -1;

    // Buka otomatis panel pembayaran saat item PERTAMA masuk keranjang
    if (state.cart.length > 0 && prevCartCount === 0 && !state.paymentExpanded) {
        togglePaymentDetails(true);
    }
    prevCartCount = state.cart.length;

    if (state.cart.length === 0) {
        wrap.innerHTML = `<div class="h-full flex flex-col items-center justify-center text-center py-12 px-4">
            <div class="w-12 h-12 rounded-full bg-zinc-100 border border-zinc-200 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                    <path d="M17 17h-11v-14h-2"/>
                    <path d="M6 5l14 1l-1 7h-13"/>
                </svg>
            </div>
            <p class="text-sm font-bold text-zinc-700">Belum ada pesanan</p>
            <p class="text-xs text-zinc-400 mt-0.5">Pilih produk di sebelah kiri untuk menambahkan</p>
        </div>`;
    } else {
        const tipeOrder = [
            { key: 'dine_in', label: 'Dine In', icon: TIPE_ICON_SVG.dine_in },
            { key: 'take_away', label: 'Take Away', icon: TIPE_ICON_SVG.take_away },
            { key: 'delivery', label: 'Delivery', icon: TIPE_ICON_SVG.delivery },
        ];
        const groups = getOrderGroups();
        const parts = [];

        tipeOrder.forEach(({ key, label, icon }) => {
            const items = groups[key];
            if (!items?.length) return;

            const collapsed = collapsedOrderGroups.has(key);
            const subTotalGrup = items.reduce((s, i) => s + subtotalItem(i), 0);

            const itemsHtml = items
                .map((i) => buildCartCard(i, state.cart.indexOf(i)))
                .join('');

            const deliveryFields = key === 'delivery'
                ? `<div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 space-y-2.5">
                    <div>
                        <label for="input-alamat-pengiriman" class="block text-xs font-bold text-zinc-600 mb-1">Alamat Pengiriman <span class="text-red-500">*</span></label>
                        <textarea id="input-alamat-pengiriman" rows="2" placeholder="Isi alamat lengkap untuk pengantaran" class="w-full text-xs rounded-xl border border-zinc-200 bg-white px-3 py-2 focus:outline-none focus:border-zinc-900 transition-colors resize-none">${escapeHtml(state.alamatPengiriman)}</textarea>
                    </div>
                    <div>
                        <label for="input-biaya-kirim" class="block text-xs font-bold text-zinc-600 mb-1">Biaya Kirim</label>
                        <input id="input-biaya-kirim" type="text" inputmode="numeric" placeholder="0" value="${state.biayaKirim ? state.biayaKirim.toLocaleString('id-ID') : ''}" class="w-full text-right text-sm font-semibold tabular-nums rounded-xl border border-zinc-200 bg-white px-3 py-2 focus:outline-none focus:border-zinc-900 transition-colors">
                    </div>
                </div>`
                : '';

            parts.push(`<div class="space-y-2">
                <button type="button" data-toggle-order-group="${key}" title="${collapsed ? 'Buka grup' : 'Tutup grup'}"
                    class="w-full flex items-center justify-between gap-2 rounded-lg px-1 py-1 cursor-pointer select-none group/og">
                    <span class="flex items-center gap-1.5 min-w-0">
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 ${collapsed ? '' : 'rotate-90'}" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4l4 4-4 4"/></svg>
                        <span class="text-xs font-bold text-zinc-800 uppercase tracking-wide truncate">${icon} ${label}</span>
                        <span class="text-[10px] font-semibold text-zinc-500 bg-zinc-100 border border-zinc-200 rounded-full px-1.5 py-0.5 tabular-nums">${items.length} item</span>
                    </span>
                    <span class="text-[11px] font-bold text-zinc-400 tabular-nums">${rupiah(subTotalGrup)}</span>
                </button>
                <div data-order-items="${key}" class="space-y-2 ${collapsed ? 'hidden' : ''}">
                    ${itemsHtml}
                    ${deliveryFields}
                </div>
            </div>`);
        });

        wrap.innerHTML = parts.join('');
    }

    const badge = document.getElementById('badge-cart-count');
    const badgeFloat = document.getElementById('badge-cart-float');
    const totalItem = state.cart.reduce((n, i) => n + i.jumlah, 0);
    if (badge) {
        badge.classList.toggle('hidden', totalItem === 0);
        badge.textContent = totalItem;
    }
    if (badgeFloat) {
        badgeFloat.classList.toggle('hidden', totalItem === 0);
        badgeFloat.textContent = totalItem;
    }

    const lblTotal = document.getElementById('lbl-total');
    if (lblTotal) lblTotal.textContent = rupiah(totalNormal());

    const lblNetoPra = document.getElementById('lbl-neto-pra');
    if (lblNetoPra) lblNetoPra.textContent = rupiah(Math.max(0, totalKotor() - nominalDiskon()));

    const lblNeto = document.getElementById('lbl-neto');
    if (lblNeto) {
        const netoBaru = rupiah(totalNeto());
        if (lblNeto.textContent !== netoBaru) {
            lblNeto.textContent = netoBaru;
            lblNeto.classList.remove('anim-pop');
            void lblNeto.offsetWidth;
            lblNeto.classList.add('anim-pop');
        }
    }

    const potonganBarang = totalPotonganBarang();
    const rowPotonganBarang = document.getElementById('row-potongan-barang');
    const lblPotonganBarang = document.getElementById('lbl-potongan-barang');
    if (rowPotonganBarang && lblPotonganBarang) {
        rowPotonganBarang.classList.toggle('hidden', potonganBarang <= 0);
        if (potonganBarang > 0) lblPotonganBarang.textContent = `- ${rupiah(potonganBarang)}`;
    }

    const hasDelivery = state.cart.some((i) => i.jenis_pesanan === 'delivery');
    const rowBiayaKirim = document.getElementById('row-biaya-kirim');
    const lblBiayaKirim = document.getElementById('lbl-biaya-kirim');
    if (rowBiayaKirim) rowBiayaKirim.classList.toggle('hidden', !hasDelivery);
    if (lblBiayaKirim) lblBiayaKirim.textContent = rupiah(state.biayaKirim || 0);

    const potonganNota = nominalDiskon();
    const rowDiskonNota = document.getElementById('row-diskon-nota');
    const lblDiskonNota = document.getElementById('lbl-diskon-nota');
    if (rowDiskonNota && lblDiskonNota) {
        rowDiskonNota.classList.toggle('hidden', potonganNota <= 0);
        lblDiskonNota.textContent = potonganNota > 0 ? `- ${rupiah(potonganNota)}` : '-Rp 0';
    }

    if (state.isUangPas) {
        state.bayar = totalNeto();
    }

    const inputDiskon = document.getElementById('input-diskon');
    if (inputDiskon && document.activeElement !== inputDiskon) inputDiskon.value = state.diskonTransaksi || '';

    const inputBayar = document.getElementById('input-bayar');
    if (inputBayar && document.activeElement !== inputBayar) inputBayar.value = state.bayar ? state.bayar.toLocaleString('id-ID') : '';

    const btnUangPas = document.getElementById('btn-uang-pas');
    if (btnUangPas) {
        btnUangPas.innerHTML = `<span class="block leading-tight">Uang pas</span><span class="block text-[10px] font-normal text-zinc-500 mt-0.5">${rupiah(totalNeto())}</span>`;
        btnUangPas.title = `Set uang bayar pas ${rupiah(totalNeto())}`;
    }

    const lblKembalian = document.getElementById('lbl-kembalian');
    if (lblKembalian) {
        if (state.jenisPembayaran === 'tunai') {
            const kurang = state.bayar > 0 && state.bayar < totalNeto();
            lblKembalian.classList.remove('hidden');
            lblKembalian.textContent = kurang
                ? `Kurang ${rupiah(totalNeto() - state.bayar)}`
                : `Kembalian ${rupiah(kembalian())}`;
            lblKembalian.classList.toggle('text-red-600', kurang);
            lblKembalian.classList.toggle('text-emerald-600', !kurang && state.bayar > 0);
        } else {
            lblKembalian.classList.add('hidden');
        }
    }

    const rowTunai = document.getElementById('row-tunai');
    const rowQris = document.getElementById('row-qris');
    const rowTransfer = document.getElementById('row-transfer');
    if (rowTunai) rowTunai.style.display = state.jenisPembayaran === 'tunai' ? '' : 'none';
    if (rowQris) rowQris.style.display = state.jenisPembayaran === 'qris' ? '' : 'none';
    if (rowTransfer) rowTransfer.style.display = state.jenisPembayaran === 'transfer' ? '' : 'none';

    const btnBayar = document.getElementById('btn-bayar');
    if (btnBayar) {
        const kosong = state.cart.length === 0;
        if (btnBayar.dataset.saving) {
            btnBayar.disabled = true;
        } else {
            btnBayar.disabled = kosong;
            btnBayar.textContent = kosong ? 'Bayar' : `Bayar · ${rupiah(totalNeto())}`;
        }
    }

    renderPaymentMethodPills();
    renderTipePicker();
}

function renderTipePicker() {
    document.querySelectorAll('[data-tipe-pesan]').forEach((btn) => {
        const aktif = btn.dataset.tipePesan === state.jenisPesanan;
        btn.classList.toggle('bg-zinc-900', aktif);
        btn.classList.toggle('text-white', aktif);
        btn.classList.toggle('border-zinc-900', aktif);
        btn.classList.toggle('shadow-xs', aktif);
        btn.classList.toggle('bg-zinc-50', !aktif);
        btn.classList.toggle('text-zinc-600', !aktif);
        btn.classList.toggle('border-zinc-200', !aktif);
        btn.classList.toggle('hover:border-zinc-400', !aktif);
        btn.classList.toggle('hover:text-zinc-900', !aktif);
    });
}

function renderGudangStokInfo() {
    const elProduk = document.getElementById('gudang-stok-produk');
    const elTotal = document.getElementById('gudang-stok-total');
    if (!elProduk && !elTotal) return;
    if (!state.gudangId) {
        if (elProduk) elProduk.textContent = '';
        if (elTotal) elTotal.textContent = '';
        return;
    }
    let produkTersedia = 0;
    let totalStok = 0;
    for (const b of state.barang) {
        const stok = Number(b.stok?.[state.gudangId] ?? 0);
        if (stok > 0) produkTersedia += 1;
        totalStok += stok;
    }
    if (elProduk) elProduk.textContent = `${produkTersedia} produk tersedia`;
    if (elTotal) elTotal.textContent = `${totalStok.toLocaleString('id-ID')} stok`;
}

function render() {
    updateCartTierPrices();
    renderProduk();
    renderCart();
    renderGudangStokInfo();
}

function fokusCartRow() {
    if (cartIdx < 0) return;
    const rows = document.querySelectorAll('#cart-items [data-cart-row]');
    if (rows[cartIdx]) {
        rows[cartIdx].focus();
        rows[cartIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function indeksSeleksiPertama() {
    return state.cart.findIndex((i) => !i.is_bonus);
}

function indeksSeleksiTerakhir() {
    for (let i = state.cart.length - 1; i >= 0; i--) {
        if (!state.cart[i].is_bonus) return i;
    }
    return -1;
}

function pindahCartIdx(delta) {
    const selectable = state.cart
        .map((i, idx) => (i.is_bonus ? -1 : idx))
        .filter((idx) => idx >= 0);
    if (selectable.length === 0) {
        cartIdx = -1;
        return;
    }
    const pos = selectable.indexOf(cartIdx);
    let nextPos;
    if (pos < 0) {
        nextPos = delta > 0 ? 0 : selectable.length - 1;
    } else {
        nextPos = Math.min(Math.max(pos + delta, 0), selectable.length - 1);
    }
    cartIdx = selectable[nextPos];
    render();
    fokusCartRow();
}

// ------------------------- TOAST -------------------------

let toastTimer;
function toast(msg, error = false) {
    const el = document.getElementById('toast');
    if (!el) return;
    const teks = escapeHtml(msg ?? '');
    el.innerHTML = error
        ? `<svg class="w-5 h-5 shrink-0 inline-block -mt-0.5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${teks}`
        : teks;
    el.className = `anim-toast fixed top-4 left-1/2 -translate-x-1/2 px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-2xl z-50 max-w-lg w-full text-center
        ${error ? 'bg-red-600/95 backdrop-blur-xs ring-1 ring-red-400/30' : 'bg-zinc-900/95 backdrop-blur-xs'}`;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 4000);
}

// ------------------------- PANDUAN SHORTCUT -------------------------

function bukaPanduanShortcut() {
    const modal = document.getElementById('modal-panduan-shortcut');
    if (!modal) return;
    panduanPrevFocus = document.activeElement;
    modal.classList.remove('hidden');
    document.getElementById('btn-tutup-panduan-shortcut')?.focus();
}

function tutupPanduanShortcut() {
    document.getElementById('modal-panduan-shortcut')?.classList.add('hidden');
    const target = panduanPrevFocus && panduanPrevFocus.isConnected ? panduanPrevFocus : null;
    panduanPrevFocus = null;
    if (target) {
        target.focus();
    } else {
        document.getElementById('input-search')?.focus();
    }
}

// ------------------------- DROPDOWN CUSTOM -------------------------

function setupDropdown(rootId, items, selectedValue, onChange, options = {}) {
    const { renderBadge } = options;
    const root = document.getElementById(rootId);
    if (!root) return { setValue: () => { } };

    const btn = root.querySelector('[data-dd-btn]');
    const lblValue = root.querySelector('[data-dd-value]');
    const chevron = root.querySelector('[data-dd-chevron]');
    if (!btn) return { setValue: () => { } };

    // Menu dibuat sebagai portal di <body> supaya tidak terjebak dalam
    // stacking context header dan tidak tertutup drawer/elemen lain.
    const menu = document.createElement('div');
    menu.setAttribute('data-dd-menu', '');
    menu.setAttribute('role', 'listbox');
    menu.className = 'hidden anim-scale-in fixed min-w-[180px] w-max max-w-[min(90vw,320px)] max-h-72 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl shadow-zinc-950/10 p-1.5';
    menu.style.zIndex = '60';
    document.body.appendChild(menu);

    let current = selectedValue;

    const itemActive =
        'dd-item w-full min-w-0 flex items-center justify-between gap-3 text-left text-sm font-bold rounded-lg px-3 py-2 bg-zinc-900 text-white cursor-pointer shadow-xs';
    const itemIdle =
        'dd-item w-full min-w-0 flex items-center justify-between gap-3 text-left text-sm font-semibold text-zinc-600 rounded-lg px-3 py-2 hover:bg-zinc-100 hover:text-zinc-900 cursor-pointer transition-colors';
    const check =
        '<svg class="w-3.5 h-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5L13 5"/></svg>';

    function setAria(expanded) {
        btn.setAttribute('aria-expanded', String(expanded));
    }

    function renderMenu() {
        if (items.length === 0) {
            menu.innerHTML = `<p class="text-xs text-zinc-500 px-3 py-2.5 whitespace-nowrap">Tidak ada data</p>`;
            if (lblValue) lblValue.textContent = '-';
            return;
        }
        menu.innerHTML = items
            .map((it) => {
                const active = String(it.value) === String(current);
                const badge = renderBadge ? renderBadge(it) : '';
                return `<button type="button" role="option" aria-selected="${active}" data-dd-val="${it.value}" class="${active ? itemActive : itemIdle}">
                    <span class="truncate min-w-0">${it.label}</span>
                    ${badge ? `<span class="shrink-0 text-[11px] font-bold whitespace-nowrap ${active ? 'text-zinc-300' : 'text-zinc-500'}">${badge}</span>` : ''}
                    ${active ? check : '<span class="w-3.5 shrink-0"></span>'}
                </button>`;
            })
            .join('');
        if (lblValue) lblValue.textContent = items.find((it) => String(it.value) === String(current))?.label ?? '';
    }

    function positionMenu() {
        const rect = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        menu.style.left = 'auto';
        if (spaceBelow >= 300) {
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.bottom = 'auto';
        } else {
            menu.style.bottom = Math.max(8, window.innerHeight - rect.top + 8) + 'px';
            menu.style.top = 'auto';
        }
        menu.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
    }

    function close() {
        menu.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
        setAria(false);
    }

    function open() {
        renderMenu();
        positionMenu();
        menu.classList.remove('hidden');
        if (chevron) chevron.classList.add('rotate-180');
        setAria(true);
    }

    function focusItem(delta) {
        const opts = Array.from(menu.querySelectorAll('[data-dd-val]'));
        if (opts.length === 0) return;
        const cur = opts.indexOf(document.activeElement);
        const next = opts[Math.min(Math.max(cur + delta, 0), opts.length - 1)];
        next.focus();
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (menu.classList.contains('hidden')) {
            open();
        } else {
            close();
        }
    });

    btn.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (menu.classList.contains('hidden')) {
                open();
            }
            focusItem(e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            e.preventDefault();
            close();
            btn.focus();
        }
    });

    menu.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            focusItem(e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            close();
            btn.focus();
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const item = e.target.closest('[data-dd-val]');
            if (item) item.click();
        }
    });

    menu.addEventListener('click', (e) => {
        const item = e.target.closest('[data-dd-val]');
        if (!item) return;
        current = item.dataset.ddVal;
        renderMenu();
        close();
        onChange(current);
    });

    const repositionSaatUbahLayar = () => {
        if (!menu.classList.contains('hidden')) positionMenu();
    };
    window.addEventListener('resize', repositionSaatUbahLayar);
    window.addEventListener('orientationchange', repositionSaatUbahLayar);

    renderMenu();
    return {
        close,
        open: () => {
            open();
            const first = menu.querySelector('[data-dd-val]');
            if (first) first.focus();
        },
        setValue: (val) => {
            current = val;
            renderMenu();
            close();
        },
    };
}

document.addEventListener('click', () => {
    document.querySelectorAll('[data-dd-menu], [data-custom-dd-menu], [data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
    document.querySelectorAll('[data-dd-btn]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    document.querySelectorAll('[data-custom-dd-btn]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    document.querySelectorAll('[data-dd-chevron], [data-custom-dd-chevron], [data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
});

function bukaModalReset() {
    const modal = document.getElementById('modal-konfirmasi-reset');
    if (!modal) return;
    const jumlahJenis = state.cart.length;
    const jumlahItem = state.cart.reduce((n, i) => n + i.jumlah, 0);
    const label = document.getElementById('label-reset-count');
    if (label) {
        label.textContent = `${jumlahJenis} jenis barang (${jumlahItem} item)`;
    }
    modal.classList.remove('hidden');
    document.getElementById('btn-batal-reset')?.focus();
}

function tutupModalReset() {
    document.getElementById('modal-konfirmasi-reset')?.classList.add('hidden');
    document.getElementById('input-search')?.focus();
}

function pasangFocusTrap(modal) {
    if (!modal) return;
    modal.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab' || modal.classList.contains('hidden')) return;
        const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const list = Array.from(focusables).filter((el) => !el.disabled && el.offsetParent !== null);
        if (list.length === 0) return;
        const first = list[0];
        const last = list[list.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });
}

// ------------------------- DRAWER KERANJANG (MOBILE) -------------------------

function bukaCart() {
    const aside = document.getElementById('cart-drawer');
    const backdrop = document.getElementById('backdrop-cart');
    const btn = document.getElementById('btn-buka-cart');
    if (aside) aside.classList.remove('translate-x-full');
    if (backdrop) backdrop.classList.remove('hidden');
    if (btn) btn.classList.add('hidden');
}

function tutupCart() {
    if (window.innerWidth >= 1024) {
        if (state.cart.length > 0) {
            bukaModalReset();
        }
        return;
    }
    const aside = document.getElementById('cart-drawer');
    const backdrop = document.getElementById('backdrop-cart');
    const btn = document.getElementById('btn-buka-cart');
    if (aside) aside.classList.add('translate-x-full');
    if (backdrop) backdrop.classList.add('hidden');
    if (btn) btn.classList.remove('hidden');
}

function renderPaymentMethodPills() {
    document.querySelectorAll('.payment-method-pill').forEach((pill) => {
        const input = pill.querySelector('input[name="jenis_pembayaran"]');
        if (!input) return;
        const isSelected = input.value === state.jenisPembayaran;
        input.checked = isSelected;
        if (isSelected) {
            pill.className = 'payment-method-pill flex-1 flex items-center justify-center text-xs font-bold rounded-md py-1.5 px-1 cursor-pointer transition-all text-center select-none bg-zinc-900 text-white shadow-xs';
        } else {
            pill.className = 'payment-method-pill flex-1 flex items-center justify-center text-xs font-bold rounded-md py-1.5 px-1 cursor-pointer transition-all text-center select-none text-zinc-600 hover:text-zinc-900';
        }
    });
}

// ------------------------- INIT -------------------------

async function init() {
    const [barang, jenis, gudang] = await Promise.all([
        getBarang(),
        getJenisBarang(),
        getGudang(),
    ]);
    state.barang = barang;
    state.jenisBarang = jenis;
    state.gudang = gudang;
    state.gudangId = gudang[0]?.id ?? null;

    let pendingGudangId = null;

    // dropdown gudang (custom) dengan konfirmasi jika keranjang tidak kosong
    const ddGudang = setupDropdown(
        'dd-gudang',
        gudang.map((g) => ({ value: g.id, label: g.nama_gudang })),
        state.gudangId,
        (val) => {
            const nextGudangId = Number(val);
            if (nextGudangId === state.gudangId) return;

            if (state.cart.length > 0) {
                pendingGudangId = nextGudangId;
                const targetGudang = state.gudang.find((g) => g.id === nextGudangId);
                const nameEl = document.getElementById('target-nama-gudang');
                if (nameEl) nameEl.textContent = targetGudang?.nama_gudang ?? 'gudang baru';
                document.getElementById('modal-konfirmasi-gudang')?.classList.remove('hidden');
            } else {
                state.gudangId = nextGudangId;
                state.cart = [];
                highlightedIdx = -1;
                render();
                const input = document.getElementById('input-search');
                if (input) input.focus();
            }
        },
        {
            renderBadge: (g) => {
                const jumlah = state.barang.filter((b) => (b.stok?.[g.value] ?? 0) > 0).length;
                return jumlah > 0 ? `${jumlah} barang` : 'stok 0';
            },
        }
    );
    gudangSetValue = ddGudang.setValue;

    // Pilih Tipe Pesanan Global
    document.querySelectorAll('[data-tipe-pesan]').forEach((btn) => {
        btn.addEventListener('click', () => setJenisPesananGlobal(btn.dataset.tipePesan));
    });

    // Modal Konfirmasi Gudang
    document.getElementById('btn-batal-gudang')?.addEventListener('click', () => {
        document.getElementById('modal-konfirmasi-gudang')?.classList.add('hidden');
        if (gudangSetValue) gudangSetValue(state.gudangId);
        pendingGudangId = null;
    });

    document.getElementById('btn-konfirmasi-gudang')?.addEventListener('click', () => {
        document.getElementById('modal-konfirmasi-gudang')?.classList.add('hidden');
        if (pendingGudangId) {
            state.gudangId = pendingGudangId;
            highlightedIdx = -1;
            resetTransaksi();
            const targetGudang = state.gudang.find((g) => g.id === pendingGudangId);
            toast(`Berhasil pindah ke ${targetGudang?.nama_gudang ?? 'gudang terpilih'}`);
            pendingGudangId = null;
            const input = document.getElementById('input-search');
            if (input) input.focus();
        }
    });

    // Modal Konfirmasi Pembayaran Non-Tunai
    document.getElementById('btn-batal-nontunai')?.addEventListener('click', tutupModalKonfirmasiNontunai);
    document.getElementById('btn-konfirmasi-nontunai')?.addEventListener('click', async () => {
        const payload = pendingNontunaiPayload;
        tutupModalKonfirmasiNontunai();
        if (payload) await simpanTransaksi(payload);
    });
    document.getElementById('modal-konfirmasi-nontunai')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) tutupModalKonfirmasiNontunai();
    });

    // Modal Konfirmasi Diskon Nota Besar
    document.getElementById('btn-batal-diskon')?.addEventListener('click', tutupModalKonfirmasiDiskon);
    document.getElementById('btn-konfirmasi-diskon')?.addEventListener('click', async () => {
        const payload = pendingDiskonPayload;
        tutupModalKonfirmasiDiskon();
        if (payload) await lanjutkanTransaksi(payload);
    });
    document.getElementById('modal-konfirmasi-diskon')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) tutupModalKonfirmasiDiskon();
    });

    const modalDiskon = document.getElementById('modal-konfirmasi-diskon');
    modalDiskon?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const batal = document.getElementById('btn-batal-diskon');
            const konfirmasi = document.getElementById('btn-konfirmasi-diskon');
            e.preventDefault();
            if (document.activeElement === batal) konfirmasi?.focus();
            else batal?.focus();
        }
    });

    const modalGudang = document.getElementById('modal-konfirmasi-gudang');
    modalGudang?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const batal = document.getElementById('btn-batal-gudang');
            const konfirmasi = document.getElementById('btn-konfirmasi-gudang');
            e.preventDefault();
            if (document.activeElement === batal) konfirmasi?.focus();
            else batal?.focus();
        }
    });

    // filter kategori
    const wrapFilter = document.getElementById('filter-jenis');
    if (wrapFilter) {
        const chipActive = 'chip-jenis shrink-0 whitespace-nowrap px-4 h-8 rounded-full text-sm font-bold bg-zinc-900 text-white shadow-sm transition cursor-pointer';
        const chipIdle = 'chip-jenis shrink-0 whitespace-nowrap px-4 h-8 rounded-full text-sm font-semibold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-900/5 transition cursor-pointer';
        wrapFilter.innerHTML =
            `<button data-jenis="" class="${chipActive}" type="button">Semua</button>` +
            jenis
                .map((j) => `<button data-jenis="${j.id}" class="${chipIdle}" type="button">${j.nama_jenis}</button>`)
                .join('');
        wrapFilter.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-jenis]');
            if (!btn) return;
            state.filterJenis = btn.dataset.jenis ? Number(btn.dataset.jenis) : null;
            highlightedIdx = -1;
            wrapFilter.querySelectorAll('.chip-jenis').forEach((b) => {
                b.className = b === btn ? chipActive : chipIdle;
            });
            renderProduk();
        });
    }

    // search
    const inputSearch = document.getElementById('input-search');
    const btnClearSearch = document.getElementById('btn-clear-search');

    if (inputSearch) {
        inputSearch.addEventListener('input', (e) => {
            state.search = e.target.value;
            highlightedIdx = -1;
            if (btnClearSearch) btnClearSearch.classList.toggle('hidden', state.search === '');
            scheduleRenderProduk();
        });
        inputSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchRenderTimer);
                const list = barangTampil();
                if (list.length > 0) {
                    tambahKeCart(list[0].id);
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                clearTimeout(searchRenderTimer);
                const list = barangTampil();
                if (list.length > 0) {
                    highlightedIdx = 0;
                    renderProduk();
                    focusSorot();
                }
            }
        });
    }

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', () => {
            state.search = '';
            highlightedIdx = -1;
            if (inputSearch) {
                inputSearch.value = '';
                inputSearch.focus();
            }
            btnClearSearch.classList.add('hidden');
            clearTimeout(searchRenderTimer);
            renderProduk();
        });
    }

    document.addEventListener('keydown', (e) => {
        const adaModalTerbuka = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-nontunai', 'modal-konfirmasi-diskon', 'modal-panduan-shortcut', 'modal-riwayat', 'modal-password-cetak'].some((id) => {
            const el = document.getElementById(id);
            return el && !el.classList.contains('hidden');
        });
        if (adaModalTerbuka) return;
        if (e.repeat) return;
        if (e.key.length !== 1 || e.ctrlKey || e.altKey || e.metaKey) return;
        if (e.key === ' ') return;
        const tag = document.activeElement?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (document.activeElement?.isContentEditable) return;
        e.preventDefault();
        const input = document.getElementById('input-search');
        const clear = document.getElementById('btn-clear-search');
        if (input) {
            input.value += e.key;
            state.search = input.value;
            highlightedIdx = -1;
            if (clear) clear.classList.toggle('hidden', false);
            scheduleRenderProduk();
            input.focus();
            const len = input.value.length;
            input.setSelectionRange(len, len);
        }
    });

    const gridProduk = document.getElementById('grid-produk');
    if (gridProduk) {
        gridProduk.addEventListener('click', (e) => {
            if (skipClick) return;
            const btn = e.target.closest('[data-add]');
            if (btn) {
                highlightedIdx = -1;
                tambahKeCart(Number(btn.dataset.add));
            }
        });
        gridProduk.addEventListener('keydown', (e) => {
            const btn = e.target.closest('[data-add]');
            if (!btn) return;

            const list = barangTampil();
            if (list.length === 0) return;
            const cols = getJmlKolom();

            let hIdx = highlightedIdx;
            if (hIdx < 0) {
                hIdx = list.findIndex((b) => b.id === Number(btn.dataset.add));
                if (hIdx < 0) hIdx = 0;
            }
            highlightedIdx = hIdx;

            if (e.key === 'ArrowRight') {
                e.preventDefault();
                highlightedIdx = Math.min(highlightedIdx + 1, list.length - 1);
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                highlightedIdx = Math.max(highlightedIdx - 1, 0);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIdx = Math.min(highlightedIdx + cols, list.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIdx = Math.max(highlightedIdx - cols, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                skipClick = true;
                setTimeout(() => { skipClick = false; }, 0);
                tambahKeCart(Number(btn.dataset.add));
                focusSorot();
                return;
            } else if (e.key === '-' || e.key === '_') {
                e.preventDefault();
                e.stopPropagation();
                const list = barangTampil();
                const barangId = Number(btn.dataset.add);
                const idx = list.findIndex((b) => b.id === barangId);
                if (idx >= 0) {
                    highlightedIdx = idx;
                    const inCart = state.cart.some((i) => i.barang_id === barangId);
                    if (!inCart) {
                        toast('Item tidak ada di keranjang. Tekan Ctrl+↓ untuk memilih di keranjang', true);
                    } else {
                        ubahJumlah(findCartLineKey(barangId), -1);
                    }
                    focusSorot();
                }
                return;
            } else if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                e.stopPropagation();
                const list = barangTampil();
                const barangId = Number(btn.dataset.add);
                const idx = list.findIndex((b) => b.id === barangId);
                if (idx >= 0) {
                    highlightedIdx = idx;
                    tambahKeCart(barangId);
                    focusSorot();
                }
                return;
            } else {
                return;
            }

            renderProduk();
            focusSorot();
        });
    }

    const cartItems = document.getElementById('cart-items');
    if (cartItems) {
        cartItems.addEventListener('click', (e) => {
            const rowEl = e.target.closest('[data-cart-row]');
            if (rowEl) {
                const idx = Number(rowEl.dataset.cartRow);
                const row = state.cart[idx];
                if (row && !row.is_bonus && idx !== cartIdx) {
                    cartIdx = idx;
                    render();
                }
            }

            const plus = e.target.closest('[data-plus]');
            const minus = e.target.closest('[data-minus]');
            const del = e.target.closest('[data-del]');
            const unitBtn = e.target.closest('[data-unit-dropdown-btn]');
            const unitSelect = e.target.closest('[data-custom-unit-select]');

            if (plus) ubahJumlah(plus.dataset.plus, 1);
            if (minus) ubahJumlah(minus.dataset.minus, -1);
            if (plus || minus) {
                setTimeout(fokusCartRow, 0);
            }
            if (del) mintaHapusItem(del.dataset.del);

            const toggleGroupBtn = e.target.closest('[data-toggle-order-group]');
            if (toggleGroupBtn) {
                e.stopPropagation();
                const key = toggleGroupBtn.dataset.toggleOrderGroup;
                if (collapsedOrderGroups.has(key)) collapsedOrderGroups.delete(key);
                else collapsedOrderGroups.add(key);
                renderCart();
                return;
            }

            if (unitBtn) {
                e.stopPropagation();
                const unitKey = unitBtn.dataset.unitDropdownBtn;
                const menu = document.querySelector(`[data-unit-dropdown-menu="${unitKey}"]`);
                const chevron = document.querySelector(`[data-unit-dropdown-chevron="${unitKey}"]`);

                const isHidden = menu?.classList.contains('hidden');

                // Close all unit menus and gudang menu
                document.querySelectorAll('[data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
                document.querySelectorAll('[data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
                document.querySelectorAll('[data-dd-menu]').forEach((m) => m.classList.add('hidden'));
                document.querySelectorAll('[data-dd-chevron]').forEach((c) => c.classList.remove('rotate-180'));

                if (isHidden && menu) {
                    menu.classList.remove('hidden');
                    if (chevron) chevron.classList.add('rotate-180');
                }
            }

            if (unitSelect) {
                e.stopPropagation();
                const unitKey = unitSelect.dataset.customUnitSelect;
                const satuan = unitSelect.dataset.unitVal;
                ubahSatuanItem(unitKey, satuan);
                setTimeout(fokusCartRow, 0);
            }
        });

        cartItems.addEventListener('input', (e) => {
            const alamatEl = e.target.closest('#input-alamat-pengiriman');
            if (alamatEl) {
                state.alamatPengiriman = alamatEl.value;
                return;
            }

            const biayaEl = e.target.closest('#input-biaya-kirim');
            if (biayaEl) {
                const el = e.target;
                const caret = el.selectionStart ?? el.value.length;
                const digitsBeforeCaret = el.value.slice(0, caret).replace(/\D/g, '').length;
                const raw = el.value.replace(/\D/g, '');
                let restorePos = 0;
                if (raw !== '') {
                    const val = Number(raw);
                    const formatted = val.toLocaleString('id-ID');
                    el.value = formatted;
                    state.biayaKirim = val;
                    let pos = 0;
                    let count = 0;
                    while (pos < formatted.length && count < digitsBeforeCaret) {
                        if (/\d/.test(formatted[pos])) count++;
                        pos++;
                    }
                    restorePos = pos;
                } else {
                    el.value = '';
                    state.biayaKirim = 0;
                }
                renderCart();
                const fresh = document.getElementById('input-biaya-kirim');
                if (fresh) {
                    fresh.focus();
                    fresh.setSelectionRange(restorePos, restorePos);
                }
                return;
            }

            const qty = e.target.closest('[data-qty]');
            if (!qty) return;
            const qtyKey = qty.dataset.qty;
            const raw = qty.value;
            const parsed = Math.floor(Number(raw));
            if (raw === '') return;
            if (!Number.isFinite(parsed) || parsed <= 0) {
                const item = state.cart.find((i) => i.key === qtyKey);
                qty.value = item ? String(item.jumlah) : '';
                qty.focus();
                return;
            }
            setJumlah(qtyKey, raw);
            const fresh = document.querySelector(`[data-qty="${qtyKey}"]`);
            if (fresh && fresh !== e.target) fresh.focus();
        });

        cartItems.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            const menuFokus = e.target.closest('[data-unit-dropdown-menu]');
            if (menuFokus) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    e.stopPropagation();
                    const opts = Array.from(menuFokus.querySelectorAll('[data-custom-unit-select]'));
                    const cur = opts.indexOf(e.target);
                    const next = opts[Math.min(Math.max(cur + (e.key === 'ArrowDown' ? 1 : -1), 0), opts.length - 1)];
                    if (next) next.focus();
                }
                return;
            }
            if (cartIdx < 0 || cartIdx >= state.cart.length) return;
            if (state.cart[cartIdx]?.is_bonus) return;
            const idKey = state.cart[cartIdx].key;

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                pindahCartIdx(-1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                pindahCartIdx(1);
            } else if (e.key === '-' || e.key === '_') {
                e.preventDefault();
                e.stopPropagation();
                ubahJumlah(idKey, -1);
                fokusCartRow();
            } else if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                e.stopPropagation();
                ubahJumlah(idKey, 1);
                fokusCartRow();
            } else if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                e.stopPropagation();
                mintaHapusItem(idKey);
            } else if (e.key === 'r' || e.key === 'R') {
                e.preventDefault();
                e.stopPropagation();
                const menu = document.querySelector(`[data-unit-dropdown-menu="${idKey}"]`);
                if (menu) {
                    document.querySelectorAll('[data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
                    document.querySelectorAll('[data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
                    menu.classList.remove('hidden');
                    const chevron = document.querySelector(`[data-unit-dropdown-chevron="${idKey}"]`);
                    if (chevron) chevron.classList.add('rotate-180');
                    const opt = menu.querySelector('[data-custom-unit-select]');
                    if (opt) opt.focus();
                }
            }
        });
    }

    const inputDiskon = document.getElementById('input-diskon');
    if (inputDiskon) {
        inputDiskon.addEventListener('input', (e) => {
            let raw = e.target.value.replace(/\D/g, '');
            if (raw !== '') {
                let val = Math.min(100, Number(raw));
                e.target.value = val;
                state.diskonTransaksi = val;
            } else {
                e.target.value = '';
                state.diskonTransaksi = 0;
            }
            renderCart();
        });
    }

    const inputBayar = document.getElementById('input-bayar');
    if (inputBayar) {
        inputBayar.addEventListener('input', (e) => {
            const el = e.target;
            const caret = el.selectionStart ?? el.value.length;
            const digitsBeforeCaret = el.value.slice(0, caret).replace(/\D/g, '').length;
            const raw = el.value.replace(/\D/g, '');
            if (raw !== '') {
                const val = Number(raw);
                const formatted = val.toLocaleString('id-ID');
                el.value = formatted;
                state.bayar = val;
                // Mengetik manual melepas kunci "uang pas" (jangan biarkan render menimpa ketikan).
                state.isUangPas = false;
                // Pulihkan posisi kursor mengikuti jumlah digit sebelum kursor sebelumnya.
                let pos = 0;
                let count = 0;
                while (pos < formatted.length && count < digitsBeforeCaret) {
                    if (/\d/.test(formatted[pos])) count++;
                    pos++;
                }
                el.setSelectionRange(pos, pos);
            } else {
                el.value = '';
                state.bayar = 0;
                state.isUangPas = false;
            }
            renderCart();
        });
    }

    document.getElementById('btn-uang-pas')?.addEventListener('click', () => {
        state.isUangPas = true;
        state.bayar = totalNeto();
        renderCart();
    });

    document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.jenisPembayaran = e.target.value;
            state.isUangPas = false;
            renderPaymentMethodPills();
            renderCart();
        });
    });

    document.querySelectorAll('input[name="bank_transfer"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.bankTransfer = e.target.value;
        });
    });

    document.getElementById('btn-toggle-payment')?.addEventListener('click', () => {
        togglePaymentDetails();
    });
    document.getElementById('btn-bayar')?.addEventListener('click', prosesBayar);
    document.getElementById('btn-preview-struk')?.addEventListener('click', () => {
        if (state.cart.length === 0) {
            toast('Keranjang masih kosong', true);
            return;
        }
        if (!state.gudangId) {
            toast('Pilih gudang dulu', true);
            return;
        }
        const preview = {
            gudang_id: state.gudangId,
            tanggal: tanggalHariIni(),
            total: totalKotor(),
            diskon: nominalDiskon(),
            neto: totalNeto(),
            biaya_kirim: state.biayaKirim || 0,
            subtotal_normal: totalNormal(),
            potongan_barang: totalPotonganBarang(),
            jenis_pembayaran: state.jenisPembayaran,
            bayar: state.jenisPembayaran === 'tunai' ? state.bayar : totalNeto(),
            kembalian: state.jenisPembayaran === 'tunai' ? kembalian() : 0,
            bank_transfer: state.jenisPembayaran === 'transfer' ? state.bankTransfer : null,
            nomer_nota: 'Preview',
            details: state.cart.map((i) => ({ ...i, subtotal: subtotalItem(i) })),
        };
        tampilkanStruk(preview);
    });

    document.getElementById('btn-reset')?.addEventListener('click', () => {
        if (state.cart.length > 0) {
            bukaModalReset();
        } else {
            toast('Keranjang masih kosong', true);
        }
    });
    document.getElementById('btn-batal-reset')?.addEventListener('click', tutupModalReset);
    document.getElementById('btn-konfirmasi-reset')?.addEventListener('click', () => {
        tutupModalReset();
        resetTransaksi();
        toast('Pesanan dikosongkan');
    });

    const modalReset = document.getElementById('modal-konfirmasi-reset');
    modalReset?.addEventListener('click', (e) => {
        if (e.target === modalReset) tutupModalReset();
    });
    modalReset?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const batal = document.getElementById('btn-batal-reset');
            const konfirmasi = document.getElementById('btn-konfirmasi-reset');
            e.preventDefault();
            if (document.activeElement === batal) konfirmasi?.focus();
            else batal?.focus();
        }
    });

    const btnBatalHapus = document.getElementById('btn-batal-hapus');
    const btnKonfirmasiHapus = document.getElementById('btn-konfirmasi-hapus');
    btnBatalHapus?.addEventListener('click', tutupModalHapus);
    btnKonfirmasiHapus?.addEventListener('click', () => {
        const id = pendingHapusId;
        document.getElementById('modal-konfirmasi-hapus')?.classList.add('hidden');
        pendingHapusId = null;
        if (id !== null) hapusItem(id);
        fokusCartRow();
    });
    const modalHapus = document.getElementById('modal-konfirmasi-hapus');
    modalHapus?.addEventListener('click', (e) => {
        if (e.target === modalHapus) tutupModalHapus();
    });
    modalHapus?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            if (document.activeElement === btnBatalHapus) btnKonfirmasiHapus?.focus();
            else btnBatalHapus?.focus();
        }
    });

    async function tutupModalStruk() {
        const modal = document.getElementById('modal-struk');
        if (modal) modal.classList.add('hidden');
        try {
            const [barang] = await Promise.all([getBarang()]);
            state.barang = barang;
        } catch (_) { }
        render();
        document.getElementById('input-search')?.focus();
    }

    const modalStruk = document.getElementById('modal-struk');
    modalStruk?.addEventListener('click', (e) => {
        if (e.target === modalStruk) tutupModalStruk();
    });
    document.getElementById('btn-tutup-struk')?.addEventListener('click', tutupModalStruk);
    document.getElementById('btn-print-struk')?.addEventListener('click', () => window.print());

    // ------------------------- SHORTCUT -------------------------
    const modalPanduan = document.getElementById('modal-panduan-shortcut');

    document.getElementById('btn-panduan-shortcut')?.addEventListener('click', bukaPanduanShortcut);
    document.getElementById('btn-mobile-shortcut')?.addEventListener('click', bukaPanduanShortcut);
    document.getElementById('btn-tutup-panduan-shortcut')?.addEventListener('click', tutupPanduanShortcut);
    document.getElementById('btn-selesai-panduan-shortcut')?.addEventListener('click', tutupPanduanShortcut);
    modalPanduan?.addEventListener('click', (e) => {
        if (e.target === modalPanduan) tutupPanduanShortcut();
    });

    // Riwayat transaksi
    document.getElementById('btn-riwayat')?.addEventListener('click', bukaModalRiwayat);
    document.getElementById('btn-mobile-riwayat')?.addEventListener('click', bukaModalRiwayat);
    document.getElementById('btn-tutup-riwayat')?.addEventListener('click', tutupModalRiwayat);
    document.getElementById('btn-tutup-riwayat-bawah')?.addEventListener('click', tutupModalRiwayat);
    document.getElementById('riwayat-tanggal')?.addEventListener('change', (e) => {
        muatRiwayat(e.target.value);
    });
    document.getElementById('btn-riwayat-hari-ini')?.addEventListener('click', () => {
        const input = document.getElementById('riwayat-tanggal');
        const tgl = tanggalHariIni();
        if (input) input.value = tgl;
        muatRiwayat(tgl);
    });
    document.getElementById('btn-riwayat-lebih')?.addEventListener('click', () => {
        if (riwayatHasMore) muatRiwayat(riwayatTanggalAktif, true);
    });

    // Filter riwayat: kasir / gudang / metode (dropdown custom seperti gudang di header)
    const reloadRiwayat = () => {
        const input = document.getElementById('riwayat-tanggal');
        muatRiwayat(riwayatTanggalAktif || input?.value || tanggalHariIni());
    };
    const kasirList = window.KASIR_DATA?.kasirList ?? [];
    setupDropdown(
        'filter-riwayat-kasir',
        [{ value: '', label: 'Semua' }, ...kasirList.map((nama) => ({ value: nama, label: nama }))],
        riwayatFilter.kasir,
        (val) => {
            riwayatFilter.kasir = val;
            reloadRiwayat();
        }
    );
    setupDropdown(
        'filter-riwayat-gudang',
        [{ value: '', label: 'Semua' }, ...state.gudang.map((g) => ({ value: String(g.id), label: g.nama_gudang }))],
        riwayatFilter.gudang_id,
        (val) => {
            riwayatFilter.gudang_id = val;
            reloadRiwayat();
        }
    );
    setupDropdown(
        'filter-riwayat-metode',
        [
            { value: '', label: 'Semua' },
            { value: 'tunai', label: 'Tunai' },
            { value: 'qris', label: 'QRIS' },
            { value: 'transfer', label: 'Transfer' },
        ],
        riwayatFilter.metode,
        (val) => {
            riwayatFilter.metode = val;
            reloadRiwayat();
        }
    );
    const modalRiwayat = document.getElementById('modal-riwayat');
    modalRiwayat?.addEventListener('click', (e) => {
        if (e.target === modalRiwayat) tutupModalRiwayat();
    });
    document.getElementById('riwayat-list')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-cetak-ulang]');
        if (btn) mintaPasswordCetak(btn.dataset.cetakUlang);
    });

    document.getElementById('form-password-cetak')?.addEventListener('submit', verifikasiPasswordCetak);
    document.getElementById('btn-batal-password-cetak')?.addEventListener('click', tutupModalPasswordCetak);
    const modalPwCetak = document.getElementById('modal-password-cetak');
    modalPwCetak?.addEventListener('click', (e) => {
        if (e.target === modalPwCetak) tutupModalPasswordCetak();
    });

    document.addEventListener('keydown', (e) => {
        const panduanTerbuka = !modalPanduan?.classList.contains('hidden');
        const mod = e.ctrlKey || e.metaKey;
        const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

        if (e.repeat) return;

        if (e.key === 'Escape' && !mod && !e.altKey) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (panduanTerbuka) {
                tutupPanduanShortcut();
                return;
            }
            const menuFokus = document.activeElement?.closest('[data-unit-dropdown-menu]');
            if (menuFokus) {
                menuFokus.classList.add('hidden');
                const idMenu = menuFokus.dataset.unitDropdownMenu;
                const chevron = document.querySelector(`[data-unit-dropdown-chevron="${idMenu}"]`);
                if (chevron) chevron.classList.remove('rotate-180');
                fokusCartRow();
                return;
            }
            const ddTerbuka = document.querySelector('[data-dd-menu]:not(.hidden), [data-custom-dd-menu]:not(.hidden)');
            if (ddTerbuka) {
                document.querySelectorAll('[data-dd-menu], [data-custom-dd-menu]').forEach((m) => {
                    m.classList.add('hidden');
                    const b = m.parentElement?.querySelector('[data-dd-btn], [data-custom-dd-btn]');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                document.querySelectorAll('[data-dd-chevron], [data-custom-dd-chevron]').forEach((c) => c.classList.remove('rotate-180'));
                return;
            }
            const urutanModal = ['modal-struk', 'modal-password-cetak', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-nontunai', 'modal-konfirmasi-diskon', 'modal-riwayat'];
            const terbuka = urutanModal.find((id) => {
                const el = document.getElementById(id);
                return el && !el.classList.contains('hidden');
            });
            if (terbuka === 'modal-password-cetak') {
                tutupModalPasswordCetak();
                return;
            }
            if (terbuka === 'modal-konfirmasi-gudang') {
                pendingGudangId = null;
                if (gudangSetValue) gudangSetValue(state.gudangId);
            }
            if (terbuka === 'modal-konfirmasi-nontunai') {
                tutupModalKonfirmasiNontunai();
                return;
            }
            if (terbuka === 'modal-konfirmasi-diskon') {
                tutupModalKonfirmasiDiskon();
                return;
            }
            if (terbuka === 'modal-struk') {
                tutupModalStruk();
                return;
            }
            if (terbuka === 'modal-konfirmasi-hapus') {
                tutupModalHapus();
                return;
            }
            if (terbuka) {
                document.getElementById(terbuka)?.classList.add('hidden');
                document.getElementById('input-search')?.focus();
                return;
            }
            const backdropCart = document.getElementById('backdrop-cart');
            if (backdropCart && !backdropCart.classList.contains('hidden')) {
                tutupCart();
                return;
            }
            if (state.search !== '') {
                state.search = '';
                const inp = document.getElementById('input-search');
                if (inp) inp.value = '';
                const clear = document.getElementById('btn-clear-search');
                if (clear) clear.classList.add('hidden');
            }
            highlightedIdx = -1;
            cartIdx = -1;
            render();
            document.getElementById('input-search')?.focus();
            return;
        }

        if (e.key === '?' && !mod && !e.altKey && !inInput) {
            if (panduanTerbuka) {
                e.preventDefault();
                e.stopImmediatePropagation();
                tutupPanduanShortcut();
                return;
            }
            const adaModalLain = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-diskon', 'modal-riwayat', 'modal-password-cetak'].some((id) => {
                const el = document.getElementById(id);
                return el && !el.classList.contains('hidden');
            });
            if (adaModalLain) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            bukaPanduanShortcut();
            return;
        }

        if (panduanTerbuka) {
            if (e.key === 'Tab') {
                const focusables = modalPanduan.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const list = Array.from(focusables).filter((el) => !el.disabled && el.offsetParent !== null);
                if (list.length > 0) {
                    const first = list[0];
                    const last = list[list.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
                return;
            }
            const isTypingChar = e.key.length === 1 && !mod && !e.altKey;
            const isAppShortcut =
                (mod && !e.altKey && ['enter', 'arrowdown', 'arrowup'].includes(e.key.toLowerCase())) ||
                ['F2', 'F3', 'F4', 'F6', 'F7', 'F8', 'F9'].includes(e.key);
            if ((isTypingChar && !inInput) || isAppShortcut) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }
        }

        const modalLainTerbuka = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-nontunai', 'modal-konfirmasi-diskon', 'modal-riwayat', 'modal-password-cetak'].some((id) => {
            const el = document.getElementById(id);
            return el && !el.classList.contains('hidden');
        });
        if (modalLainTerbuka) return;

        if (e.key === 'F2') {
            e.preventDefault();
            e.stopImmediatePropagation();
            const input = document.getElementById('input-search');
            if (input) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
            return;
        }

        if (e.key === 'F3') {
            e.preventDefault();
            e.stopImmediatePropagation();
            bukaModalRiwayat();
            return;
        }

        if (e.key === 'F4') {
            e.preventDefault();
            e.stopImmediatePropagation();
            const urutan = ['tunai', 'qris', 'transfer'];
            const idx = urutan.indexOf(state.jenisPembayaran);
            state.jenisPembayaran = urutan[(idx + 1) % urutan.length];
            state.bayar = 0;
            state.isUangPas = false;
            document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
                radio.checked = radio.value === state.jenisPembayaran;
            });
            if (!state.paymentExpanded) togglePaymentDetails(true);
            renderCart();
            return;
        }

        if (e.key === 'F6') {
            e.preventDefault();
            e.stopImmediatePropagation();
            togglePaymentDetails();
            return;
        }

        if (e.key === 'F7') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            if (!state.paymentExpanded) togglePaymentDetails(true);
            state.bayar = totalNeto();
            renderCart();
            return;
        }

        if (e.key === 'F8') {
            e.preventDefault();
            e.stopImmediatePropagation();
            muatUlangData();
            return;
        }

        if (e.key === 'F9') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length > 0) {
                bukaModalReset();
            } else {
                toast('Keranjang masih kosong', true);
            }
            return;
        }

        if (mod && !e.altKey && e.key === 'ArrowUp') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            cartIdx = indeksSeleksiPertama();
            render();
            fokusCartRow();
            return;
        }

        if (mod && !e.altKey && e.key === 'ArrowDown') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            cartIdx = indeksSeleksiTerakhir();
            render();
            fokusCartRow();
            return;
        }

        if (mod && !e.altKey && e.key === 'Enter') {
            e.preventDefault();
            e.stopImmediatePropagation();
            prosesBayar();
            return;
        }
    }, true);

    if (USE_MOCK) {
        document.getElementById('badge-mock')?.classList.remove('hidden');
    }

    ['modal-struk', 'modal-riwayat', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-konfirmasi-hapus', 'modal-konfirmasi-nontunai', 'modal-konfirmasi-diskon', 'modal-password-cetak'].forEach((id) => {
        pasangFocusTrap(document.getElementById(id));
    });

    document.getElementById('loading')?.classList.add('hidden');
    document.getElementById('kasir-app')?.classList.remove('hidden');
    render();

    // jam & tanggal berjalan di header
    updateJamHeader();
    setInterval(updateJamHeader, 1000);

    // drawer keranjang (mobile)
    document.getElementById('btn-buka-cart')?.addEventListener('click', bukaCart);
    document.getElementById('backdrop-cart')?.addEventListener('click', tutupCart);

    // Konfirmasi keluar/refresh bila masih ada pesanan belum dibayar
    document.querySelector('form[action*="kasir/logout"]')?.addEventListener('submit', () => {
        allowUnload = true;
    });
    window.addEventListener('beforeunload', (e) => {
        if (allowUnload || state.cart.length === 0) return;
        e.preventDefault();
        e.returnValue = '';
    });

    const inpAwal = document.getElementById('input-search');
    if (inpAwal) {
        inpAwal.focus();
        inpAwal.setSelectionRange(inpAwal.value.length, inpAwal.value.length);
    }

    setInterval(refreshStokSilent, AUTO_REFRESH_MS);
}

init().catch((e) => {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.innerHTML = `
            <div class="flex-1 flex flex-col items-center justify-center gap-2 text-center px-6">
                <p class="text-sm font-bold text-zinc-900">Gagal memuat data</p>
                <p class="text-sm text-zinc-600">${e.message}</p>
                <button onclick="location.reload()"
                    class="mt-3 text-sm font-bold bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl px-5 py-2.5 transition">
                    Muat ulang
                </button>
            </div>`;
    }
});
