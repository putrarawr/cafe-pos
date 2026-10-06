<div class="sesi-meja-modal-container" style="font-size:14px;color:#e4e4e7">

    @php
        $sesiTerbaru = $record->sesi->sortByDesc('mulai')->values();
        $totalSesi = $sesiTerbaru->count();
        $totalOver = $sesiTerbaru->where('overstay', true)->count();
        $rataDurasi = $totalSesi > 0
            ? (int) round($sesiTerbaru->avg(fn ($s) => $s->durasi_menit_terpakai ?: $s->lamaMenit()))
            : 0;
    @endphp

    {{-- HEADER CARD --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;padding:16px;border-radius:12px;background:#27272a;border:1px solid #3f3f46;margin-bottom:16px">
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Kode Meja</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->kode_meja }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Nama / Area</p>
            <p style="font-size:15px;font-weight:800;color:#fafafa;letter-spacing:-0.01em">{{ $record->nama_meja }}</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Kapasitas / Durasi</p>
            <p style="font-size:15px;font-weight:800;color:#38bdf8">{{ $record->kapasitas }} org &middot; {{ $record->durasi_menit }} mnt</p>
        </div>
        <div>
            <p style="font-size:10px;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Rata-rata Durasi</p>
            <p style="font-size:15px;font-weight:800;color:#34d399">{{ $rataDurasi }} menit</p>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:12px">
        <span style="padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)">
            {{ $totalSesi }} pemakaian
        </span>
        <span style="padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)">
            {{ $totalOver }} overstay
        </span>
    </div>

    {{-- TABEL RIWAYAT SESI MEJA --}}
    <div style="border-radius:12px;border:1px solid #3f3f46;overflow:hidden;max-height:360px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">
            <thead style="background:#27272a;position:sticky;top:0;z-index:10">
                <tr style="border-bottom:1px solid #3f3f46;color:#a1a1aa">
                    <th style="padding:10px 14px">Mulai</th>
                    <th style="padding:10px 14px">Selesai</th>
                    <th style="padding:10px 14px;text-align:right">Durasi</th>
                    <th style="padding:10px 14px">Referensi</th>
                    <th style="padding:10px 14px;text-align:center;width:110px">Status</th>
                </tr>
            </thead>
            <tbody style="background:#18181b">
                @forelse($sesiTerbaru as $sesi)
                    @php
                        $durasi = $sesi->durasi_menit_terpakai ?: $sesi->lamaMenit();
                        $referensi = $sesi->orderPending?->kode_order
                            ?? $sesi->penjualan?->nomer_nota
                            ?? '-';
                        $statusText = $sesi->status === \App\Models\SesiMeja::STATUS_TERISI
                            ? 'TERISI'
                            : ($sesi->status === \App\Models\SesiMeja::STATUS_BATAL ? 'BATAL' : 'SELESAI');
                        $statusStyle = match ($sesi->status) {
                            \App\Models\SesiMeja::STATUS_TERISI => 'background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)',
                            \App\Models\SesiMeja::STATUS_BATAL => 'background:rgba(113,113,122,0.15);color:#a1a1aa;border:1px solid rgba(113,113,122,0.3)',
                            default => 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)',
                        };
                    @endphp
                    <tr style="border-bottom:1px solid #27272a">
                        <td style="padding:10px 14px;color:#d4d4d8">{{ $sesi->mulai?->format('d M Y H:i') }}</td>
                        <td style="padding:10px 14px;color:#a1a1aa">{{ $sesi->selesai?->format('d M Y H:i') ?? '-' }}</td>
                        <td style="padding:10px 14px;text-align:right;font-weight:700;color:#38bdf8">{{ $durasi }} mnt</td>
                        <td style="padding:10px 14px;color:#a1a1aa">{{ $referensi }}</td>
                        <td style="padding:10px 14px;text-align:center">
                            <span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;{{ $statusStyle }}">
                                {{ $statusText }}
                            </span>
                            @if($sesi->overstay)
                                <span style="display:block;margin-top:4px;font-size:10px;font-weight:700;color:#fbbf24">OVER</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:24px;text-align:center;color:#71717a">
                            Meja ini belum pernah dipakai.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
