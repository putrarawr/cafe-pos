@php
    $totalTransaksi = $invoices->count();
    $totalQtyAll = $invoices->sum(fn ($inv) => $inv->details->sum('jumlah'));
    $totalTunai = $invoices->where('jenis_pembayaran', 'tunai')->sum('neto');
    $totalQris = $invoices->where('jenis_pembayaran', 'qris')->sum('neto');
    $totalTransfer = $invoices->whereIn('jenis_pembayaran', ['transfer', 'bank', 'debit'])->sum('neto');
    $totalOmset = $invoices->sum('neto');

    $isTunaiDimmed = (isset($activeKategori) && $activeKategori !== null && $activeKategori !== 'tunai');
    $isQrisDimmed = (isset($activeKategori) && $activeKategori !== null && $activeKategori !== 'qris');
    $isTransferDimmed = (isset($activeKategori) && $activeKategori !== null && $activeKategori !== 'transfer');
    $dimStyle = 'opacity: 0.38; filter: grayscale(0.6); transition: all 0.2s ease-in-out;';
@endphp

<style>
    .rekapan-kasir-modal {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #f4f4f5;
    }
    .rekapan-kasir-header-card {
        background: #18181b;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .rekapan-kasir-table-wrapper {
        max-height: 420px;
        overflow-y: auto;
        overflow-x: auto;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        background: #18181b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .rekapan-kasir-table-wrapper th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 1px 0 #3f3f46;
    }
    .rekapan-kasir-table-wrapper tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 -1px 0 #3f3f46;
    }
</style>

<div class="rekapan-kasir-modal">
    {{-- Header Info / Ringkasan Kasir --}}
    <div class="rekapan-kasir-header-card">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #3f3f46;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin: 0;">
                        {{ $kasirName }}
                    </h3>
                </div>
                <div style="font-size: 11px; color: #a1a1aa; margin-top: 4px;">
                    Periode: <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($dariTanggal)->format('d M Y') }}</strong> s/d <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($sampaiTanggal)->format('d M Y') }}</strong>
                </div>
            </div>
            <div style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 600; color: #d4d4d8;">
                Total: <span style="color: #ffffff; font-weight: 700;">{{ number_format($totalTransaksi, 0, ',', '.') }} Faktur</span>
            </div>
        </div>

        {{-- 4 Kartu Ringkasan Metode Pembayaran --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
            {{-- Tunai --}}
            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px; {{ $isTunaiDimmed ? $dimStyle : '' }}">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Tunai</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalTunai, 0, ',', '.') }}
                </div>
            </div>

            {{-- QRIS --}}
            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px; {{ $isQrisDimmed ? $dimStyle : '' }}">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">QRIS</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalQris, 0, ',', '.') }}
                </div>
            </div>

            {{-- Bank / Transfer --}}
            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px; {{ $isTransferDimmed ? $dimStyle : '' }}">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Bank / Transfer</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalTransfer, 0, ',', '.') }}
                </div>
            </div>

            {{-- Total Omset --}}
            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Total Omset</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalOmset, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Faktur Penjualan --}}
    @if ($invoices->isEmpty())
        <div style="text-align: center; padding: 32px; background: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; color: #a1a1aa; font-size: 12px;">
            Tidak ada data transaksi faktur penjualan{{ !empty($activeKategori) ? ' untuk kategori ' . strtoupper($activeKategori) : '' }} pada periode ini.
        </div>
    @else
        <div class="rekapan-kasir-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.4; min-width: 860px;">
                <thead>
                    <tr style="background: #27272a; color: #f4f4f5;">
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 10px; text-align: center; width: 38px; font-weight: 700; font-size: 11px; text-transform: uppercase;">No</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">No. Entry & Item Barang</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Waktu Transaksi</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: center; font-weight: 700; font-size: 11px; text-transform: uppercase;">Metode Pembayaran</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: center; font-weight: 700; font-size: 11px; text-transform: uppercase;">Total Qty</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Total Faktur</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Bayar / Kembali</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $idx => $inv)
                        @php
                            $rowBg = ($idx % 2 === 0) ? '#18181b' : '#202024';
                            $qtyItem = $inv->details->sum('jumlah');
                            $itemsSummary = $inv->details->map(function ($d) {
                                return ($d->barang?->nama_barang ?? 'Item') . ' (' . number_format($d->jumlah, 0, ',', '.') . ')';
                            })->take(3)->implode(', ');
                            if ($inv->details->count() > 3) {
                                $itemsSummary .= ' + ' . ($inv->details->count() - 3) . ' lainnya';
                            }
                            $metode = strtolower($inv->jenis_pembayaran ?? 'tunai');
                        @endphp
                        <tr
                            style="background: {{ $rowBg }}; border-bottom: 1px solid #27272a;"
                            onmouseover="this.style.background='#27272f'"
                            onmouseout="this.style.background='{{ $rowBg }}'"
                        >
                            <td style="padding: 8px 10px; text-align: center; color: #71717a; font-family: monospace;">
                                {{ $idx + 1 }}
                            </td>
                            <td style="padding: 8px 12px;">
                                <div style="font-family: monospace; font-weight: 700; color: #fafafa;">
                                    {{ $inv->nomer_nota ?? '-' }}
                                </div>
                                <div style="font-size: 11px; color: #a1a1aa; margin-top: 2px;">
                                    {{ $itemsSummary ?: 'Tidak ada item' }}
                                </div>
                            </td>
                            <td style="padding: 8px 12px; color: #d4d4d8; white-space: nowrap;">
                                {{ $inv->created_at ? $inv->created_at->format('d M Y H:i') : '-' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: center; white-space: nowrap;">
                                @if ($metode === 'tunai')
                                    <span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                        TUNAI
                                    </span>
                                @elseif ($metode === 'qris')
                                    <span style="background: rgba(37, 99, 235, 0.15); border: 1px solid rgba(37, 99, 235, 0.35); color: #60a5fa; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                        QRIS
                                    </span>
                                @else
                                    <span style="background: rgba(168, 85, 247, 0.15); border: 1px solid rgba(168, 85, 247, 0.35); color: #c084fc; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                        {{ strtoupper($inv->jenis_pembayaran ?? 'TRANSFER') }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #ffffff;">
                                {{ number_format($qtyItem, 0, ',', '.') }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #34d399; white-space: nowrap;">
                                Rp {{ number_format($inv->neto, 0, ',', '.') }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; white-space: nowrap;">
                                @if ($metode === 'tunai')
                                    <div style="font-size: 11px; color: #d4d4d8;">
                                        Bayar: <strong style="color: #ffffff;">Rp {{ number_format($inv->bayar ?? $inv->neto, 0, ',', '.') }}</strong>
                                    </div>
                                    <div style="font-size: 10px; color: #a1a1aa;">
                                        Kembali: Rp {{ number_format($inv->kembalian ?? 0, 0, ',', '.') }}
                                    </div>
                                @else
                                    <span style="font-size: 11px; color: #71717a;">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #27272a; font-weight: 700; color: #f4f4f5; border-top: 2px solid #3f3f46;">
                        <td colspan="4" style="padding: 10px 12px; text-align: right; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">
                            Total Keseluruhan ({{ number_format($totalTransaksi, 0, ',', '.') }} Faktur):
                        </td>
                        <td style="padding: 10px 12px; text-align: center; color: #60a5fa; font-size: 13px; font-weight: 800;">
                            {{ number_format($totalQtyAll, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; text-align: right; color: #34d399; font-size: 13px; font-weight: 800; white-space: nowrap;">
                            Rp {{ number_format($totalOmset, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
