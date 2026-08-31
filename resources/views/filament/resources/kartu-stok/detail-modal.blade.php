<div style="font-size:14px;color:#e4e4e7">

    {{-- HEADER CARD --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:16px">
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nomer Entry</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->nomer_entry ?? '-' }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Waktu Transaksi</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">
                {{ \Carbon\Carbon::parse($record->created_at ?? $record->tanggal)->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
            </p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nama Barang</p>
            <p style="font-size:13px;font-weight:700;color:#fafafa">
                {{ $record->barang->nama_barang ?? '-' }}
                @if(!empty($record->barang?->nomer_seri))
                    <span style="font-size:11px;color:#a1a1aa;font-weight:500">({{ $record->barang->nomer_seri }})</span>
                @endif
            </p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Gudang</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">{{ $record->gudang->nama_gudang ?? '-' }}</p>
        </div>
    </div>

    {{-- RINCIAN MUTASI --}}
    <div style="padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:16px">
        <p style="font-size:11px;color:#71717a;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Rincian Mutasi Stok</p>

        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#71717a;margin-bottom:8px">
            <span>Jenis Mutasi</span>
            @php
                $jenisStyle = match($record->jenis_transaksi) {
                    'masuk', 'pindah_masuk' => 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)',
                    'keluar', 'pindah_keluar' => 'background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)',
                    default => 'background:#3f3f46;color:#a1a1aa;border:1px solid #52525b',
                };
            @endphp
            <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;{{ $jenisStyle }}">
                {{ str_replace('_', ' ', $record->jenis_transaksi) }}
            </span>
        </div>

        @php
            $satuan = $record->barang?->satuan ?? 'Pcs';
        @endphp
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#71717a;margin-bottom:8px">
            <span>Perubahan Jumlah</span>
            <span style="font-weight:700;font-size:14px;color:{{ $record->jumlah > 0 ? '#34d399' : '#f87171' }}">
                {{ $record->jumlah > 0 ? '+' . number_format($record->jumlah, 0, ',', '.') : number_format($record->jumlah, 0, ',', '.') }} {{ $satuan }}
            </span>
        </div>

        <div style="display:flex;justify-content:space-between;font-size:12px;color:#71717a;margin-bottom:8px">
            <span>Saldo Akhir Stok</span>
            <span style="font-weight:800;font-size:14px;color:#38bdf8">{{ number_format($record->saldo, 0, ',', '.') }} {{ $satuan }}</span>
        </div>

        @if($record->harga > 0)
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#71717a">
                <span>Harga Per Satuan</span>
                <span style="font-weight:600;color:#d4d4d8">Rp {{ number_format($record->harga, 0, ',', '.') }} / {{ $satuan }}</span>
            </div>
        @endif
    </div>

    {{-- KETERANGAN LENGKAP --}}
    <div style="padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46">
        <p style="font-size:11px;color:#71717a;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px">Keterangan Lengkap</p>
        <p style="font-size:13px;color:#fafafa;line-height:1.6;white-space:pre-wrap;word-break:break-word">
            {{ $record->keterangan ?? 'Tidak ada keterangan tambahan.' }}
        </p>
    </div>

</div>
