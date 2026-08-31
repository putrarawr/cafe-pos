<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #e4e4e7;">
    {{-- Header Info / Ringkasan Stat --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px;">
        {{-- Card 1: Info Barang --}}
        <div style="background: #18181b; border: 1px solid #3f3f46; border-radius: 8px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Informasi Barang</div>
            <div style="margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                <span style="background: #27272a; border: 1px solid #52525b; border-radius: 4px; padding: 2px 6px; font-family: monospace; font-size: 11px; font-weight: 700; color: #fafafa;">
                    {{ $barang->nomer_seri ?? '-' }}
                </span>
                <span style="font-weight: 700; font-size: 13px; color: #ffffff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ $barang->nama_barang }}
                </span>
            </div>
            <div style="margin-top: 4px; font-size: 11px; color: #71717a;">
                Satuan Dasar: <strong style="color: #d4d4d8;">{{ $barang->satuan ?? 'Pcs' }}</strong>
            </div>
        </div>

        {{-- Card 2: Harga Beli Terakhir --}}
        <div style="background: #18181b; border: 1px solid #3f3f46; border-radius: 8px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Harga Beli Terakhir</div>
            <div style="margin-top: 4px; font-size: 16px; font-weight: 800; color: #ffffff;">
                Rp {{ number_format($barang->harga_beli ?? 0, 0, ',', '.') }}
                <span style="font-size: 11px; font-weight: 400; color: #a1a1aa;">/ {{ $barang->satuan ?? 'Pcs' }}</span>
            </div>
            <div style="margin-top: 4px; font-size: 11px; color: #71717a;">
                Berdasarkan pembelian terakhir
            </div>
        </div>

        {{-- Card 3: HPP Average Saat Ini --}}
        <div style="background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.35); border-radius: 8px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.05em;">HPP Average Saat Ini</div>
            <div style="margin-top: 4px; font-size: 18px; font-weight: 800; color: #38bdf8;">
                Rp {{ number_format($barang->hpp ?? $barang->harga_beli ?? 0, 2, ',', '.') }}
            </div>
            <div style="margin-top: 4px; font-size: 11px; color: rgba(56, 189, 248, 0.8);">
                Rata-rata bergerak per {{ $barang->satuan ?? 'Pcs' }}
            </div>
        </div>
    </div>

    {{-- Tabel Grid Excel Style --}}
    @if ($histori->isEmpty())
        <div style="text-align: center; padding: 32px; background: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; color: #a1a1aa; font-size: 12px;">
            Belum ada catatan riwayat transaksi pembelian untuk barang ini.
        </div>
    @else
        <div style="overflow-x: auto; border: 2px solid #52525b; border-radius: 8px; background: #18181b;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.4; min-width: 860px;">
                <thead>
                    <tr style="background: #27272a; color: #f4f4f5;">
                        <th style="border: 1px solid #52525b; padding: 8px 6px; text-align: center; width: 36px; font-weight: 700; font-size: 11px; text-transform: uppercase;">No</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: left; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase;">Tanggal & Jam</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: left; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase;">No. Entry</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: left; min-width: 130px; font-weight: 700; font-size: 11px; text-transform: uppercase;">Supplier</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase;">Qty Beli</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase;">Harga Beli Satuan</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase; background: #2f2f35;">Stok Sebelum</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase; background: #2f2f35;">Stok Sesudah</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase;">HPP Lama</th>
                        <th style="border: 1px solid #52525b; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; font-size: 11px; text-transform: uppercase; background: rgba(14, 165, 233, 0.2); color: #38bdf8;">HPP Baru</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($histori as $index => $item)
                        @php
                            $satuanBeli = $item->satuan ?? $barang->satuan ?? 'Pcs';
                            $faktor = $barang->getFaktorKonversi($satuanBeli);
                            $isMultiUnit = ($faktor > 1 && $barang->satuan && $barang->satuan !== $satuanBeli);
                            $rowBg = ($index % 2 === 0) ? '#18181b' : '#202024';
                        @endphp
                        <tr style="background: {{ $rowBg }}; border-bottom: 1px solid #3f3f46;" onmouseover="this.style.background='#27272e'" onmouseout="this.style.background='{{ $rowBg }}'">
                            {{-- No --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 6px; text-align: center; color: #71717a; font-family: monospace;">
                                {{ $index + 1 }}
                            </td>

                            {{-- Tanggal & Jam --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; white-space: nowrap;">
                                <div style="font-weight: 600; color: #f4f4f5;">
                                    {{ $item->tanggal ? $item->tanggal->format('d/m/Y') : '-' }}
                                </div>
                                <div style="font-size: 11px; color: #71717a;">
                                    {{ $item->tanggal ? $item->tanggal->format('H:i') : '' }} WIB
                                </div>
                            </td>

                            {{-- No. Entry --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; white-space: nowrap; font-family: monospace; font-weight: 700; color: #d4d4d8;">
                                {{ $item->nomer_entry ?? '-' }}
                            </td>

                            {{-- Supplier --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; color: #e4e4e7; font-weight: 500;">
                                {{ $item->supplier?->nama_supplier ?? '-' }}
                            </td>

                            {{-- Qty Beli --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap;">
                                <div style="font-weight: 700; color: #ffffff; font-variant-numeric: tabular-nums;">
                                    {{ number_format($item->qty_beli, 0, ',', '.') }} {{ $satuanBeli }}
                                </div>
                                @if ($isMultiUnit)
                                    <div style="font-size: 10px; color: #a1a1aa; font-weight: normal; font-variant-numeric: tabular-nums;">
                                        ({{ number_format($item->qty_beli * $faktor, 0, ',', '.') }} {{ $barang->satuan }})
                                    </div>
                                @endif
                            </td>

                            {{-- Harga Beli Satuan --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap;">
                                <div style="font-weight: 700; color: #ffffff; font-variant-numeric: tabular-nums;">
                                    Rp {{ number_format($item->harga_beli_masuk, 0, ',', '.') }}
                                    <span style="font-size: 10px; font-weight: normal; color: #a1a1aa;">/ {{ $satuanBeli }}</span>
                                </div>
                                @if ($isMultiUnit)
                                    <div style="font-size: 10px; color: #a1a1aa; font-weight: normal; font-variant-numeric: tabular-nums;">
                                        (Rp {{ number_format(round($item->harga_beli_masuk / $faktor), 0, ',', '.') }} / {{ $barang->satuan }})
                                    </div>
                                @endif
                            </td>

                            {{-- Stok Sebelum --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap; color: #a1a1aa; background: rgba(0,0,0,0.15); font-variant-numeric: tabular-nums;">
                                {{ number_format($item->stok_sebelum, 0, ',', '.') }} {{ $barang->satuan ?? 'Pcs' }}
                            </td>

                            {{-- Stok Sesudah --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 700; color: #fafafa; background: rgba(0,0,0,0.15); font-variant-numeric: tabular-nums;">
                                {{ number_format($item->stok_sesudah, 0, ',', '.') }} {{ $barang->satuan ?? 'Pcs' }}
                            </td>

                            {{-- HPP Lama --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap; color: #a1a1aa; font-variant-numeric: tabular-nums;">
                                Rp {{ number_format($item->hpp_sebelum, 2, ',', '.') }}
                            </td>

                            {{-- HPP Baru --}}
                            <td style="border: 1px solid #3f3f46; padding: 8px 10px; text-align: right; white-space: nowrap; font-weight: 800; color: #38bdf8; background: rgba(14, 165, 233, 0.1); font-variant-numeric: tabular-nums;">
                                Rp {{ number_format($item->hpp_sesudah, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
