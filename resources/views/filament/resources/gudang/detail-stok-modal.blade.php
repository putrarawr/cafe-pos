<div class="stok-modal-container" style="font-size:14px;color:#e4e4e7">

    @php
        $barangs = $record->barangs ?? collect();
        $totalStok = $barangs->sum(fn ($b) => $b->pivot->stok ?? 0);
    @endphp

    {{-- HEADER CARD --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:16px">
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nama Gudang</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->nama_gudang }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Alamat Gudang</p>
            <p style="font-size:13px;font-weight:600;color:#d4d4d8">{{ $record->alamat ?? '-' }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Total Jenis Barang</p>
            <p style="font-size:14px;font-weight:700;color:#38bdf8">{{ $barangs->count() }} Jenis</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Total Saldo Stok</p>
            <p style="font-size:14px;font-weight:800;color:#34d399">{{ number_format($totalStok, 0, ',', '.') }} Item</p>
        </div>
    </div>

    {{-- SEARCH INPUT & FILTER --}}
    <div style="margin-bottom:12px">
        <input 
            type="text" 
            placeholder="Cari nama barang atau jenis..." 
            oninput="const q = this.value.toLowerCase().trim(); this.closest('.stok-modal-container').querySelectorAll('.row-barang-gudang').forEach(r => r.style.display = (r.getAttribute('data-search') || '').includes(q) ? '' : 'none')"
            style="width:100%;padding:10px 14px;background:#18181b;border:1px solid #3f3f46;border-radius:8px;color:#fafafa;font-size:13px;outline:none"
        >
    </div>

    {{-- TABEL STOK BARANG --}}
    <div style="border-radius:12px;border:1px solid #3f3f46;overflow:hidden;max-height:360px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">
            <thead style="background:#27272a;position:sticky;top:0;z-index:10">
                <tr style="border-bottom:1px solid #3f3f46;color:#a1a1aa">
                    <th style="padding:10px 14px;width:40px;text-align:center">No</th>
                    <th style="padding:10px 14px">Nama Barang</th>
                    <th style="padding:10px 14px">Kategori</th>
                    <th style="padding:10px 14px;text-align:right">Saldo Stok</th>
                    <th style="padding:10px 14px;text-align:center;width:100px">Status</th>
                </tr>
            </thead>
            <tbody style="background:#18181b">
                @forelse($barangs as $index => $barang)
                    @php
                        $stok = $barang->pivot->stok ?? 0;
                        $satuan = $barang->satuan ?? 'Pcs';
                        $jenisNama = $barang->jenisBarang->nama_jenis ?? '-';
                        $statusText = $stok > 5 ? 'Tersedia' : ($stok > 0 ? 'Menipis' : 'Habis');
                        $statusStyle = match(true) {
                            $stok > 5 => 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)',
                            $stok > 0 => 'background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)',
                            default => 'background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)',
                        };
                    @endphp
                    <tr class="row-barang-gudang" data-search="{{ strtolower($barang->nama_barang . ' ' . $jenisNama) }}" style="border-bottom:1px solid #27272a">
                        <td style="padding:10px 14px;text-align:center;color:#71717a">{{ $index + 1 }}</td>
                        <td style="padding:10px 14px;font-weight:700;color:#fafafa">{{ $barang->nama_barang }}</td>
                        <td style="padding:10px 14px;color:#a1a1aa">{{ $jenisNama }}</td>
                        <td style="padding:10px 14px;text-align:right;font-weight:800;color:#38bdf8">
                            {{ number_format($stok, 0, ',', '.') }} <span style="font-size:11px;font-weight:600;color:#a1a1aa">{{ $satuan }}</span>
                        </td>
                        <td style="padding:10px 14px;text-align:center">
                            <span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;{{ $statusStyle }}">
                                {{ $statusText }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:24px;text-align:center;color:#71717a">
                            Belum ada barang di gudang ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
