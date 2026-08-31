<div class="jenis-modal-container" style="font-size:14px;color:#e4e4e7">

    @php
        $barangs = $record->barangs ?? collect();
    @endphp

    {{-- HEADER CARD --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:16px">
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nama Jenis / Kategori</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->nama_jenis }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Total Barang</p>
            <p style="font-size:14px;font-weight:800;color:#38bdf8">{{ $barangs->count() }} Barang</p>
        </div>
        <div style="grid-column:span 2">
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Deskripsi</p>
            <p style="font-size:13px;color:#d4d4d8;line-height:1.5">{{ $record->deskripsi ?? 'Tidak ada deskripsi' }}</p>
        </div>
    </div>

    {{-- SEARCH INPUT & FILTER --}}
    <div style="margin-bottom:12px">
        <input 
            type="text" 
            placeholder="Cari nama barang..." 
            oninput="const q = this.value.toLowerCase().trim(); this.closest('.jenis-modal-container').querySelectorAll('.row-barang-jenis').forEach(r => r.style.display = (r.getAttribute('data-search') || '').includes(q) ? '' : 'none')"
            style="width:100%;padding:10px 14px;background:#18181b;border:1px solid #3f3f46;border-radius:8px;color:#fafafa;font-size:13px;outline:none"
        >
    </div>

    {{-- TABEL RINGKAS BARANG --}}
    <div style="border-radius:12px;border:1px solid #3f3f46;overflow:hidden;max-height:360px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">
            <thead style="background:#27272a;position:sticky;top:0;z-index:10">
                <tr style="border-bottom:1px solid #3f3f46;color:#a1a1aa">
                    <th style="padding:10px 14px;width:50px;text-align:center">No</th>
                    <th style="padding:10px 14px">Nama Barang</th>
                </tr>
            </thead>
            <tbody style="background:#18181b">
                @forelse($barangs as $index => $barang)
                    <tr class="row-barang-jenis" data-search="{{ strtolower($barang->nama_barang) }}" style="border-bottom:1px solid #27272a">
                        <td style="padding:10px 14px;text-align:center;color:#71717a">{{ $index + 1 }}</td>
                        <td style="padding:10px 14px;font-weight:700;color:#fafafa">{{ $barang->nama_barang }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="padding:24px;text-align:center;color:#71717a">
                            Belum ada barang dalam kategori ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
