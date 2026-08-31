@props([
    'transaction' => [],
    'promoBonus' => [],
])

@php
    $invoiceNumber = $transaction['invoice_number'] ?? $transaction['nomer_nota'] ?? 'PJ-00000000-0000';
    $cashierName   = $transaction['cashier_name'] ?? $transaction['nama_kasir'] ?? 'Kasir';
    $createdAt     = $transaction['created_at'] ?? $transaction['tanggal'] ?? date('d/m/Y H:i:s');
    $storeName     = $transaction['store_name'] ?? $transaction['toko']['nama'] ?? 'Toko PKL';
    $warehouseName = $transaction['warehouse_name'] ?? $transaction['nama_gudang'] ?? 'Gudang Utama - Pusat';
    
    $items         = $transaction['items'] ?? $transaction['details'] ?? [];
    $subtotal      = $transaction['subtotal'] ?? $transaction['subtotal_normal'] ?? 0;
    $discount      = $transaction['discount_amount'] ?? $transaction['potongan_barang'] ?? $transaction['diskon'] ?? 0;
    $total         = $transaction['total'] ?? $transaction['neto'] ?? 0;
    $paymentAmount = $transaction['payment_amount'] ?? $transaction['bayar'] ?? 0;
    $changeAmount  = $transaction['change_amount'] ?? $transaction['kembalian'] ?? max(0, $paymentAmount - $total);
    $paymentType   = $transaction['payment_type'] ?? $transaction['jenis_pembayaran'] ?? 'tunai';

    $paymentTypeLabel = match(strtolower($paymentType)) {
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
        default => 'Tunai',
    };

    $bonusByParent = [];
    $bonusNoParent = [];
    foreach ($items as $item) {
        if (empty($item['is_bonus'])) continue;
        $promoId = $item['promo_id'] ?? null;
        $found = false;
        if ($promoId) {
            foreach ($promoBonus as $promo) {
                if (($promo['id'] ?? $promo->id ?? null) == $promoId) {
                    $parentId = $promo['barang_utama_id'] ?? $promo->barang_utama_id ?? null;
                    if ($parentId) {
                        $bonusByParent[$parentId][] = $item;
                        $found = true;
                    }
                    break;
                }
            }
        }
        if (!$found) {
            $bonusNoParent[] = $item;
        }
    }

    $groupedItems = [];
    foreach ($items as $item) {
        if (!empty($item['is_bonus'])) continue;
        $groupedItems[] = $item;
        $barangId = $item['barang_id'] ?? $item['id'] ?? null;
        if (isset($bonusByParent[$barangId])) {
            foreach ($bonusByParent[$barangId] as $bonusItem) {
                $groupedItems[] = $bonusItem;
            }
        }
    }
    foreach ($bonusNoParent as $bonusItem) {
        $groupedItems[] = $bonusItem;
    }
@endphp

<div class="receipt-wrapper font-sans antialiased text-zinc-900 bg-white p-4 max-w-sm mx-auto rounded-2xl shadow-sm border border-zinc-100">
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable-receipt, .printable-receipt * { visibility: visible; }
            .printable-receipt {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 4mm !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
            }
            .no-print { display: none !important; }
        }
    </style>

    <div class="printable-receipt">
        {{-- HEADER --}}
        <div class="text-center">
            <h1 class="text-lg font-bold text-zinc-900 tracking-tight">{{ $storeName }}</h1>
            <p class="text-xs text-zinc-700 font-normal mt-0.5">{{ $warehouseName }}</p>
            <p class="text-xs text-zinc-700 font-normal mt-0.5">Nota: {{ $invoiceNumber }} &bull; {{ $createdAt }}</p>
            <p class="text-xs text-zinc-700 font-normal mt-0.5">Kasir: {{ $cashierName }}</p>
        </div>

        {{-- DIVIDER --}}
        <div class="border-b border-dashed border-zinc-400 my-3"></div>

        {{-- ITEMS LIST (2-Line per item layout) --}}
        <div class="space-y-3">
            @foreach($groupedItems as $item)
                @php
                    $namaBarang   = $item['name'] ?? $item['nama_barang'] ?? '-';
                    $jumlah       = $item['qty'] ?? $item['jumlah'] ?? 1;
                    $satuan       = $item['unit'] ?? $item['satuan'] ?? 'Pcs';
                    $hargaUnit    = $item['price'] ?? $item['harga_asli'] ?? $item['harga'] ?? 0;
                    $itemSubtotal = $item['subtotal'] ?? ($jumlah * $hargaUnit);
                    $potongan     = $item['discount'] ?? $item['diskon'] ?? 0;
                    $isBonus      = !empty($item['is_bonus']);
                    $potonganPct  = ($jumlah * $hargaUnit > 0) ? round(($potongan / ($jumlah * $hargaUnit)) * 100) : 0;
                @endphp
                <div>
                    <p class="font-bold text-sm text-zinc-900 leading-tight">{{ $namaBarang }}</p>
                    <div class="flex justify-between items-start text-xs mt-1">
                        <span class="text-zinc-700">{{ $jumlah }} {{ $satuan }} @if(!$isBonus) x Rp {{ number_format($hargaUnit, 0, ',', '.') }} @endif</span>
                        <div class="text-right">
                            <span class="font-bold text-sm text-zinc-900">Rp {{ number_format($itemSubtotal, 0, ',', '.') }}</span>
                            @if($isBonus)
                                <span class="text-xs font-bold text-zinc-900 block text-right">BONUS (GRATIS @if($namaPromo)&bull; {{ $namaPromo }}@endif)</span>
                            @elseif($potongan > 0)
                                <span class="text-xs font-normal text-zinc-600 block text-right mt-0.5">potongan -{{ $potonganPct }}%</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div>
                        <p class="font-bold text-sm text-zinc-900 leading-tight">{{ $namaBarang }}</p>
                        <div class="flex justify-between items-start text-xs mt-1">
                            <span class="text-zinc-700">{{ $jumlah }} {{ $satuan }} x Rp {{ number_format($hargaUnit, 0, ',', '.') }}</span>
                            <div class="text-right">
                                <span class="font-bold text-sm text-zinc-900">Rp {{ number_format($itemSubtotal, 0, ',', '.') }}</span>
                                @if($potongan > 0)
                                    <span class="text-xs font-normal text-zinc-600 block text-right mt-0.5">potongan -{{ $potonganPct }}%</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- DIVIDER --}}
        <div class="border-b border-dashed border-zinc-400 my-3"></div>

        {{-- SUMMARY --}}
        <div class="space-y-1.5 text-xs text-zinc-800">
            <div class="flex justify-between items-center">
                <span>Subtotal</span>
                <span class="text-zinc-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
            </div>

            @if($discount > 0)
                <div class="flex justify-between items-center text-xs font-normal text-zinc-800">
                    <span>Potongan Barang (Total Rp):</span>
                    <span class="text-zinc-900">- Rp {{ number_format($discount, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        {{-- DIVIDER --}}
        <div class="border-b border-dashed border-zinc-400 my-3"></div>

        {{-- TOTALS & PAYMENT --}}
        <div class="space-y-1.5 text-xs">
            <div class="flex justify-between items-baseline text-zinc-900 font-bold text-base">
                <span>Total</span>
                <span>Rp {{ number_format($total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center text-zinc-800 mt-2">
                <span>Bayar ({{ $paymentTypeLabel }})</span>
                <span class="text-zinc-900">Rp {{ number_format($paymentAmount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center text-zinc-800 mt-2">
                <span>Kembalian</span>
                <span class="font-bold text-sm text-zinc-900">Rp {{ number_format($changeAmount, 0, ',', '.') }}</span>
            </div>
        </div>

        @php
            $bonusItemsInStruk = array_filter($items, fn($it) => !empty($it['is_bonus']));
        @endphp
        @if(count($bonusItemsInStruk) > 0)
            <div class="border-t border-dashed border-zinc-400 pt-3 mt-3 space-y-1">
                <p class="text-xs font-bold text-zinc-900">Catatan Promo &amp; Bonus Pelanggan:</p>
                @foreach($bonusItemsInStruk as $bItem)
                    @php
                        $bNama = $bItem['name'] ?? $bItem['nama_barang'] ?? 'Barang';
                        $bQty  = $bItem['qty'] ?? $bItem['jumlah'] ?? 1;
                        $bUnit = $bItem['unit'] ?? $bItem['satuan'] ?? 'Pcs';
                        $bPromo= $bItem['nama_promo'] ?? null;
                    @endphp
                    <p class="text-xs text-zinc-800 font-medium">• <strong>{{ $bNama }}</strong> {{ $bQty }} {{ $bUnit }} &mdash; Gratis @if($bPromo)({{ $bPromo }})@endif</p>
                @endforeach
            </div>
        @endif

        {{-- DIVIDER --}}
        <div class="border-b border-dashed border-zinc-400 my-4"></div>

        {{-- FOOTER MESSAGE --}}
        <p class="text-center text-xs text-zinc-400 font-normal my-4">Terima kasih atas kunjungan Anda</p>
    </div>

    {{-- ACTION BUTTONS (Hidden when printed) --}}
    <div class="no-print flex gap-2.5 mt-6">
        <button type="button" onclick="window.print()"
            class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer text-center">
            Cetak Struk
        </button>
        <button type="button" onclick="location.reload()"
            class="flex-1 border border-zinc-200 hover:border-zinc-900 text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer text-center">
            Transaksi Baru
        </button>
    </div>
</div>
