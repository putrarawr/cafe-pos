<div style="font-size:14px;color:#e4e4e7">

    {{-- HEADER PEMBELIAN --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:20px">
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nomor Entry</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->nomer_entry }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Tanggal Transaksi</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">{{ \Carbon\Carbon::parse($record->tanggal)->format('d M Y') }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Supplier</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">{{ $record->supplier->nama_supplier ?? '-' }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Gudang Tujuan</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">{{ $record->gudang->nama_gudang ?? '-' }}</p>
        </div>
    </div>

    {{-- TABEL DETAIL BARANG MASUK --}}
    <p style="font-size:13px;font-weight:700;color:#d4d4d8;margin-bottom:8px">Rincian Barang Masuk</p>
    <div style="border-radius:12px;border:1px solid #3f3f46;overflow:hidden;margin-bottom:20px">
        <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">
            <thead>
                <tr style="background:#18181b;border-bottom:2px solid #3f3f46">
                    <th style="padding:10px 12px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.06em">Barang</th>
                    <th style="padding:10px 12px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.06em;text-align:right">Harga Beli</th>
                    <th style="padding:10px 12px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.06em;text-align:center">Qty</th>
                    <th style="padding:10px 12px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.06em;text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->details as $detail)
                    <tr style="border-bottom:1px solid #27272a">
                        <td style="padding:10px 12px;font-weight:600;color:#fafafa">
                            {{ $detail->barang->nama_barang ?? 'Barang Dihapus' }}
                            <span style="display:block;font-size:10px;color:#71717a;margin-top:2px">({{ $detail->satuan ?? 'pcs' }})</span>
                        </td>
                        <td style="padding:10px 12px;text-align:right;color:#a1a1aa;font-variant-numeric:tabular-nums">
                            Rp {{ number_format($detail->harga, 0, ',', '.') }}
                        </td>
                        <td style="padding:10px 12px;text-align:center">
                            <span style="display:inline-block;min-width:24px;padding:2px 8px;background:rgba(249,115,22,0.15);color:#fb923c;font-weight:700;border-radius:6px;font-size:12px;font-variant-numeric:tabular-nums">
                                {{ $detail->jumlah }}
                            </span>
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;color:#fafafa;font-variant-numeric:tabular-nums">
                            Rp {{ number_format($detail->subtotal ?? ($detail->jumlah * $detail->harga), 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- RINGKASAN PEMBAYARAN --}}
    <div style="padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46">

        <div style="display:flex;justify-content:space-between;font-size:12px;color:#71717a;margin-bottom:6px">
            <span>Total Pembelian</span>
            <span style="font-weight:600;color:#a1a1aa;font-variant-numeric:tabular-nums">Rp {{ number_format($record->total, 0, ',', '.') }}</span>
        </div>

        @if($record->diskon > 0)
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#f87171;margin-bottom:6px">
                <span>Diskon</span>
                <span style="font-weight:600;font-variant-numeric:tabular-nums">-Rp {{ number_format($record->diskon, 0, ',', '.') }}</span>
            </div>
        @endif

        <div style="display:flex;justify-content:space-between;align-items:baseline;padding-top:10px;margin-top:8px;border-top:1px solid #3f3f46">
            <span style="font-size:13px;font-weight:700;color:#d4d4d8">Total Neto</span>
            <span style="font-size:18px;font-weight:900;color:#fb923c;font-variant-numeric:tabular-nums">Rp {{ number_format($record->neto, 0, ',', '.') }}</span>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#71717a;padding-top:10px;margin-top:10px;border-top:1px solid #3f3f4680">
            <span>Pembayaran</span>
            @php
                $badgeStyle = match($record->jenis_pembayaran) {
                    'tunai' => 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)',
                    'transfer' => 'background:rgba(14,165,233,0.15);color:#38bdf8;border:1px solid rgba(14,165,233,0.3)',
                    'tempo' => 'background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)',
                    default => 'background:#3f3f46;color:#a1a1aa;border:1px solid #52525b',
                };
            @endphp
            <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;{{ $badgeStyle }}">
                {{ $record->jenis_pembayaran }}
            </span>
        </div>

        @if($record->keterangan)
            <div style="padding-top:10px;margin-top:10px;border-top:1px solid #3f3f4680;font-size:12px">
                <span style="color:#71717a;display:block;margin-bottom:2px">Keterangan:</span>
                <span style="color:#d4d4d8">{{ $record->keterangan }}</span>
            </div>
        @endif
    </div>

</div>
