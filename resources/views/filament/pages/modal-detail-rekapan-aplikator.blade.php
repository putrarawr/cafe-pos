@php
    $totalTransaksi = $invoices->count();
    $totalQtyAll = $invoices->sum(fn ($inv) => $inv->details ? $inv->details->sum('jumlah') : 0);
    $totalOmset = $invoices->sum('neto');
    $totalKomisi = $invoices->sum(fn ($inv) => (float) ($inv->komisi_aplikator ?? 0));
    $totalKirim = $invoices->sum(fn ($inv) => (float) ($inv->biaya_kirim ?? 0));
    $totalBersih = $totalOmset - $totalKirim - $totalKomisi;
    $baseTanpaKirim = $totalOmset - $totalKirim;
    $avgKomisi = $baseTanpaKirim > 0 ? round(($totalKomisi / $baseTanpaKirim) * 100, 1) : 0;
    $fmtPersen = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',');
@endphp

<style>
    .rekapan-aplikator-modal {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #f4f4f5;
    }
    .rekapan-aplikator-header-card {
        background: #18181b;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .rekapan-aplikator-table-wrapper {
        max-height: 420px;
        overflow-y: auto;
        overflow-x: auto;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        background: #18181b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .rekapan-aplikator-table-wrapper th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 1px 0 #3f3f46;
    }
    .rekapan-aplikator-table-wrapper tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 -1px 0 #3f3f46;
    }
</style>

<div class="rekapan-aplikator-modal">
    {{-- Header Info / Ringkasan Aplikator --}}
    <div class="rekapan-aplikator-header-card">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #3f3f46;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin: 0;">
                        {{ $aplikator->nama_aplikator }}
                    </h3>
                    @if ($aplikator->kode_aplikator)
                        <span style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; color: #d4d4d8; text-transform: uppercase;">
                            {{ $aplikator->kode_aplikator }}
                        </span>
                    @endif
                </div>
                <div style="font-size: 11px; color: #a1a1aa; margin-top: 4px;">
                    Periode: <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($dariTanggal)->format('d M Y') }}</strong> s/d <strong style="color: #e4e4e7;">{{ \Carbon\Carbon::parse($sampaiTanggal)->format('d M Y') }}</strong>
                    &bull; Komisi master <strong style="color: #e4e4e7;">{{ $fmtPersen($aplikator->persentase_komisi) }}%</strong>
                    &bull; Rata-rata komisi <strong style="color: #e4e4e7;">{{ $fmtPersen($avgKomisi) }}%</strong>
                </div>
            </div>
            <div style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 600; color: #d4d4d8;">
                Total: <span style="color: #ffffff; font-weight: 700;">{{ number_format($totalTransaksi, 0, ',', '.') }} Faktur</span>
            </div>
        </div>

        {{-- 4 Kartu Ringkasan --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Omset</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalOmset, 0, ',', '.') }}
                </div>
            </div>

            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Komisi Aplikator</div>
                <div style="font-size: 17px; font-weight: 800; color: #fbbf24; margin-top: 2px;">
                    Rp {{ number_format($totalKomisi, 0, ',', '.') }}
                </div>
            </div>

            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Estimasi Bersih</div>
                <div style="font-size: 17px; font-weight: 800; color: #34d399; margin-top: 2px;">
                    Rp {{ number_format($totalBersih, 0, ',', '.') }}
                </div>
            </div>

            <div style="background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Biaya Kirim</div>
                <div style="font-size: 17px; font-weight: 800; color: #ffffff; margin-top: 2px;">
                    Rp {{ number_format($totalKirim, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Faktur Penjualan --}}
    @if ($invoices->isEmpty())
        <div style="text-align: center; padding: 32px; background: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; color: #a1a1aa; font-size: 12px;">
            Tidak ada transaksi {{ $aplikator->nama_aplikator }} pada periode ini.
        </div>
    @else
        <div class="rekapan-aplikator-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.4; min-width: 900px;">
                <thead>
                    <tr style="background: #27272a; color: #f4f4f5;">
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 10px; text-align: center; width: 38px; font-weight: 700; font-size: 11px; text-transform: uppercase;">No</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">No. Nota & Item</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Waktu Transaksi</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase;">Kasir</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: center; font-weight: 700; font-size: 11px; text-transform: uppercase;">Total Qty</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Omset</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Komisi</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Kirim</th>
                        <th style="border-bottom: 1px solid #3f3f46; padding: 9px 12px; text-align: right; font-weight: 700; font-size: 11px; text-transform: uppercase;">Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $idx => $inv)
                        @php
                            $rowBg = ($idx % 2 === 0) ? '#18181b' : '#202024';
                            $qtyItem = $inv->details ? $inv->details->sum('jumlah') : 0;
                            $komisi = (float) ($inv->komisi_aplikator ?? 0);
                            $kirim = (float) ($inv->biaya_kirim ?? 0);
                            $bersih = (float) $inv->neto - $kirim - $komisi;
                            $itemsSummary = $inv->details ? $inv->details->map(function ($d) {
                                return ($d->barang?->nama_barang ?? 'Item') . ' (' . number_format($d->jumlah, 0, ',', '.') . ')';
                            })->take(3)->implode(', ') : '';
                            if ($inv->details && $inv->details->count() > 3) {
                                $itemsSummary .= ' + ' . ($inv->details->count() - 3) . ' lainnya';
                            }
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
                            <td style="padding: 8px 12px; color: #d4d4d8; white-space: nowrap;">
                                {{ $inv->nama_kasir }}
                            </td>
                            <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #ffffff;">
                                {{ number_format($qtyItem, 0, ',', '.') }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #ffffff; white-space: nowrap;">
                                Rp {{ number_format($inv->neto, 0, ',', '.') }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #fbbf24; white-space: nowrap;">
                                {{ $komisi > 0 ? '-Rp ' . number_format($komisi, 0, ',', '.') : '-' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #d4d4d8; white-space: nowrap;">
                                {{ $kirim > 0 ? '-Rp ' . number_format($kirim, 0, ',', '.') : '-' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 700; color: #34d399; white-space: nowrap;">
                                Rp {{ number_format($bersih, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #27272a; font-weight: 700; color: #f4f4f5; border-top: 2px solid #3f3f46;">
                        <td colspan="4" style="padding: 10px 12px; text-align: right; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">
                            Total ({{ number_format($totalTransaksi, 0, ',', '.') }} Faktur):
                        </td>
                        <td style="padding: 10px 12px; text-align: center; color: #60a5fa; font-size: 13px; font-weight: 800;">
                            {{ number_format($totalQtyAll, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; text-align: right; color: #ffffff; font-size: 13px; font-weight: 800; white-space: nowrap;">
                            Rp {{ number_format($totalOmset, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; text-align: right; color: #fbbf24; font-size: 13px; font-weight: 800; white-space: nowrap;">
                            -Rp {{ number_format($totalKomisi, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; text-align: right; color: #d4d4d8; font-size: 13px; font-weight: 800; white-space: nowrap;">
                            -Rp {{ number_format($totalKirim, 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; text-align: right; color: #34d399; font-size: 13px; font-weight: 800; white-space: nowrap;">
                            Rp {{ number_format($totalBersih, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
