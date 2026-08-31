@php
    $totalQtyReguler = $penjualanReguler->sum('jumlah');
    $totalSubtotalReguler = $penjualanReguler->sum('subtotal');
    $totalQtyBonus = $keluarBonus->sum('bonus_qty');
    $totalFisik = $totalQtyReguler + $totalQtyBonus;
    $satuan = $barang->satuan ?? 'Pcs';
@endphp

<style>
    .rekapan-container {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #f4f4f5;
    }
    .rekapan-header-card {
        background: #18181b;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .rekapan-tab-nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 14px;
        border-bottom: 1px solid #3f3f46;
        padding-bottom: 10px;
    }
    .rekapan-pill-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 6px 14px !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
        border: 1px solid transparent !important;
        line-height: 1 !important;
        outline: none !important;
    }
    .rekapan-pill-btn.active-reguler {
        background-color: #2563eb !important;
        color: #ffffff !important;
        border-color: #3b82f6 !important;
        font-weight: 700 !important;
        box-shadow: 0 1px 4px rgba(37, 99, 235, 0.4) !important;
    }
    .rekapan-pill-btn.active-bonus {
        background-color: #d97706 !important;
        color: #ffffff !important;
        border-color: #f59e0b !important;
        font-weight: 700 !important;
        box-shadow: 0 1px 4px rgba(217, 119, 6, 0.4) !important;
    }
    .rekapan-pill-btn.inactive {
        background-color: #18181b !important;
        color: #a1a1aa !important;
        border-color: #3f3f46 !important;
        font-weight: 600 !important;
    }
    .rekapan-pill-btn.inactive:hover {
        background-color: #27272a !important;
        color: #f4f4f5 !important;
    }
    .rekapan-count-badge {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 2px 6px !important;
        border-radius: 4px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        font-family: monospace !important;
        line-height: 1 !important;
    }
    .rekapan-count-badge.badge-active {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
    }
    .rekapan-count-badge.badge-inactive {
        background: #27272a !important;
        color: #d4d4d8 !important;
        border: 1px solid #3f3f46 !important;
    }
    .rekapan-table-wrapper {
        max-height: 420px;
        overflow-y: auto;
        overflow-x: auto;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        background: #18181b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .rekapan-table-wrapper th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 1px 0 #3f3f46;
    }
    .rekapan-table-wrapper tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 -1px 0 #3f3f46;
    }
</style>

<div x-data="{ tab: 'reguler' }" class="rekapan-container">
    {{-- Header Info / Ringkasan Stat --}}
    <div class="rekapan-header-card">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #3f3f46;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    @if($barang->nomer_seri)
                        <span style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 2px 8px; font-family: monospace; font-size: 11px; font-weight: 700; color: #fafafa;">
                            {{ $barang->nomer_seri }}
                        </span>
                    @endif
                    <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin: 0;">
                        {{ $barang->nama_barang }}
                    </h3>
                </div>
                <div style="font-size: 11px; color: #a1a1aa; margin-top: 4px;">
                    Periode: <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($dariTanggal)->format('d M Y') }}</strong> s/d <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($sampaiTanggal)->format('d M Y') }}</strong>
                </div>
            </div>
            <div style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 600; color: #d4d4d8;">
                Satuan Dasar: <span style="color: #ffffff; font-weight: 700;">{{ $satuan }}</span>
            </div>
        </div>

        {{-- 3 Kartu Ringkasan --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
            {{-- Terjual --}}
            <div style="background: rgba(37, 99, 235, 0.1); border: 1px solid rgba(37, 99, 235, 0.35); border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 600; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.05em;">Terjual</div>
                <div style="font-size: 18px; font-weight: 800; color: #93c5fd; margin-top: 2px;">
                    {{ number_format($totalQtyReguler, 0, ',', '.') }} <span style="font-size: 12px; font-weight: 600; color: #60a5fa;">{{ $satuan }}</span>
                </div>
            </div>

            {{-- Bonus --}}
            <div style="background: rgba(217, 119, 6, 0.1); border: 1px solid rgba(217, 119, 6, 0.35); border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 600; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em;">Bonus</div>
                <div style="font-size: 18px; font-weight: 800; color: #fde68a; margin-top: 2px;">
                    {{ number_format($totalQtyBonus, 0, ',', '.') }} <span style="font-size: 12px; font-weight: 600; color: #fbbf24;">{{ $satuan }}</span>
                </div>
            </div>

            {{-- Total Pengurangan Fisik --}}
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 600; color: #34d399; text-transform: uppercase; letter-spacing: 0.05em;">Total Pengurangan Fisik</div>
                <div style="font-size: 18px; font-weight: 800; color: #a7f3d0; margin-top: 2px;">
                    {{ number_format($totalFisik, 0, ',', '.') }} <span style="font-size: 12px; font-weight: 600; color: #34d399;">{{ $satuan }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigasi Tab --}}
    <div class="rekapan-tab-nav">
        <div style="display: flex; align-items: center; gap: 8px;">
            {{-- Tab Penjualan --}}
            <button
                type="button"
                @click="tab = 'reguler'"
                class="rekapan-pill-btn"
                :class="tab === 'reguler' ? 'active-reguler' : 'inactive'"
            >
                <span>Penjualan</span>
                <span
                    class="rekapan-count-badge"
                    :class="tab === 'reguler' ? 'badge-active' : 'badge-inactive'"
                >
                    {{ $penjualanReguler->count() }}
                </span>
            </button>

            {{-- Tab Bonus --}}
            <button
                type="button"
                @click="tab = 'bonus'"
                class="rekapan-pill-btn"
                :class="tab === 'bonus' ? 'active-bonus' : 'inactive'"
            >
                <span>Bonus</span>
                <span
                    class="rekapan-count-badge"
                    :class="tab === 'bonus' ? 'badge-active' : 'badge-inactive'"
                >
                    {{ $keluarBonus->count() }}
                </span>
            </button>
        </div>
    </div>

    {{-- Konten Tab 1: Penjualan Reguler --}}
    <div x-show="tab === 'reguler'">
        @if ($penjualanReguler->isEmpty())
            <div style="text-align: center; padding: 32px; background: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; color: #a1a1aa; font-size: 12px;">
                Tidak ada catatan penjualan reguler pada periode ini.
            </div>
        @else
            <div class="rekapan-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.4; min-width: 720px;">
                    <thead>
                        <tr style="background: #27272a; color: #f4f4f5;">
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 10px; text-align: center; width: 38px; font-weight: 700; font-size: 11px; text-transform: uppercase;">No</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">No. Entry</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Waktu Transaksi</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Kasir</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: center; font-weight: 700; font-size: 11px; text-transform: uppercase;">Qty Terjual</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Harga Satuan</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($penjualanReguler as $idx => $item)
                            @php
                                $rowBg = ($idx % 2 === 0) ? '#18181b' : '#202024';
                            @endphp
                            <tr
                                style="background: {{ $rowBg }}; border-bottom: 1px solid #27272a;"
                                onmouseover="this.style.background='#27272f'"
                                onmouseout="this.style.background='{{ $rowBg }}'"
                            >
                                <td style="padding: 8px 10px; text-align: center; color: #71717a; font-family: monospace;">
                                    {{ $idx + 1 }}
                                </td>
                                <td style="padding: 8px 12px; font-family: monospace; font-weight: 700; color: #fafafa;">
                                    {{ $item->penjualan?->nomer_nota ?? '-' }}
                                </td>
                                <td style="padding: 8px 12px; color: #d4d4d8; white-space: nowrap;">
                                    {{ $item->created_at ? $item->created_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td style="padding: 8px 12px; color: #e4e4e7;">
                                    {{ $item->penjualan?->nama_kasir ?? '-' }}
                                </td>
                                <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #ffffff;">
                                    {{ number_format($item->jumlah, 0, ',', '.') }} {{ $item->satuan ?? $satuan }}
                                </td>
                                <td style="padding: 8px 12px; text-align: right; color: #d4d4d8; white-space: nowrap;">
                                    Rp {{ number_format($item->harga, 0, ',', '.') }}
                                </td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #34d399; white-space: nowrap;">
                                    Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background: #27272a; font-weight: 700; color: #f4f4f5; border-top: 2px solid #3f3f46;">
                            <td colspan="4" style="padding: 10px 12px; text-align: right; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">
                                Total Penjualan Reguler:
                            </td>
                            <td style="padding: 10px 12px; text-align: center; color: #60a5fa; font-size: 13px; font-weight: 800;">
                                {{ number_format($totalQtyReguler, 0, ',', '.') }} {{ $satuan }}
                            </td>
                            <td style="padding: 10px 12px;"></td>
                            <td style="padding: 10px 12px; text-align: right; color: #34d399; font-size: 13px; font-weight: 800; white-space: nowrap;">
                                Rp {{ number_format($totalSubtotalReguler, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- Konten Tab 2: Keluar sebagai Bonus Promo --}}
    <div x-show="tab === 'bonus'">
        @if ($keluarBonus->isEmpty())
            <div style="text-align: center; padding: 32px; background: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; color: #a1a1aa; font-size: 12px;">
                Tidak ada data pengeluaran sebagai bonus promo pada periode ini.
            </div>
        @else
            <div class="rekapan-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.4; min-width: 720px;">
                    <thead>
                        <tr style="background: #27272a; color: #f4f4f5;">
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 10px; text-align: center; width: 38px; font-weight: 700; font-size: 11px; text-transform: uppercase;">No</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">No. Entry</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Waktu Transaksi</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Kasir</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Barang Utama Pembeli</th>
                            <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: center; font-weight: 700; font-size: 11px; text-transform: uppercase;">Qty Bonus Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keluarBonus as $idx => $item)
                            @php
                                $rowBg = ($idx % 2 === 0) ? '#18181b' : '#202024';
                            @endphp
                            <tr
                                style="background: {{ $rowBg }}; border-bottom: 1px solid #27272a;"
                                onmouseover="this.style.background='#27272f'"
                                onmouseout="this.style.background='{{ $rowBg }}'"
                            >
                                <td style="padding: 8px 10px; text-align: center; color: #71717a; font-family: monospace;">
                                    {{ $idx + 1 }}
                                </td>
                                <td style="padding: 8px 12px; font-family: monospace; font-weight: 700; color: #fafafa;">
                                    {{ $item->penjualan?->nomer_nota ?? '-' }}
                                </td>
                                <td style="padding: 8px 12px; color: #d4d4d8; white-space: nowrap;">
                                    {{ $item->created_at ? $item->created_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td style="padding: 8px 12px; color: #e4e4e7;">
                                    {{ $item->penjualan?->nama_kasir ?? '-' }}
                                </td>
                                <td style="padding: 8px 12px; color: #f4f4f5;">
                                    <div style="font-weight: 700;">{{ $item->barang?->nama_barang ?? '-' }}</div>
                                    <div style="font-size: 11px; color: #a1a1aa; margin-top: 2px;">(Beli: {{ number_format($item->jumlah, 0, ',', '.') }} {{ $item->satuan ?? 'Pcs' }})</div>
                                </td>
                                <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #fbbf24;">
                                    {{ number_format($item->bonus_qty, 0, ',', '.') }} {{ $item->bonus_satuan ?? $satuan }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background: #27272a; font-weight: 700; color: #f4f4f5; border-top: 2px solid #3f3f46;">
                            <td colspan="5" style="padding: 10px 12px; text-align: right; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">
                                Total Bonus Keluar:
                            </td>
                            <td style="padding: 10px 12px; text-align: center; color: #fbbf24; font-size: 13px; font-weight: 800;">
                                {{ number_format($totalQtyBonus, 0, ',', '.') }} {{ $satuan }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
