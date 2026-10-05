@php
    $barang = $record->barang;
    $gudang = $record->gudang;
    $totalStokAll = $allGudangStocks->sum('stok');
    $totalMinAll = $allGudangStocks->sum('stok_minimum');
    $totalDefisitAll = $allGudangStocks->sum(fn ($g) => $g->defisit);
@endphp

<style>
    .kontrol-stok-modal {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #f4f4f5;
    }
    .kontrol-stok-header-card {
        background: #18181b;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .kontrol-stok-table-wrapper {
        max-height: 320px;
        overflow-y: auto;
        overflow-x: auto;
        border: 1px solid #3f3f46;
        border-radius: 8px;
        background: #18181b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        margin-bottom: 20px;
    }
    .kontrol-stok-table-wrapper th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 1px 0 #3f3f46;
        padding: 12px 14px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #e4e4e7;
    }
    .kontrol-stok-table-wrapper td {
        padding: 12px 14px;
        font-size: 12.5px;
        border-bottom: 1px solid #27272a;
    }
    .kontrol-stok-table-wrapper tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #27272a;
        box-shadow: 0 -1px 0 #3f3f46;
        font-weight: 700;
        color: #ffffff;
    }
</style>

<div class="kontrol-stok-modal">
    {{-- Header Kartu Rincian Barang & Lokasi Terpilih --}}
    <div class="kontrol-stok-header-card">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #27272a;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 0;">
                        {{ $barang?->nama_barang ?? 'Barang' }}
                    </h3>
                    @if($barang?->barcode)
                        <span style="background: #202024; border: 1px solid #3f3f46; color: #a1a1aa; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                            {{ $barang->barcode }}
                        </span>
                    @endif
                </div>
                <div style="font-size: 12px; color: #a1a1aa; margin-top: 4px;">
                    Kategori: <strong style="color: #e4e4e7;">{{ $barang?->jenisBarang?->nama_jenis ?? '-' }}</strong>
                    &bull; Satuan: <strong style="color: #e4e4e7;">{{ $barang?->satuan ?? 'Pcs' }}</strong>
                    @if($barang?->satuan_2)
                        / {{ $barang->satuan_2 }}
                    @endif
                </div>
            </div>

            <div>
                <a href="{{ $createMutasiUrl }}" target="_blank"
                   style="display: inline-flex; align-items: center; gap: 8px; background: #27272a; border: 1px solid #3f3f46; color: #ffffff; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; transition: background 0.2s;"
                   onmouseover="this.style.background='#3f3f46'" onmouseout="this.style.background='#27272a'">
                    <span>Buat Perpindahan Barang</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        {{-- Ringkasan Status di Gudang Aktif --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
            <div style="background: #111113; border: 1px solid #27272a; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 10px; font-weight: 700; color: #a1a1aa; text-transform: uppercase;">Gudang Ini</div>
                <div style="font-size: 14px; font-weight: 700; color: #38bdf8; margin-top: 2px;">{{ $gudang?->nama_gudang }}</div>
            </div>

            <div style="background: #111113; border: 1px solid #27272a; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 10px; font-weight: 700; color: #a1a1aa; text-transform: uppercase;">Sisa Stok</div>
                <div style="font-size: 14px; font-weight: 700; color: #ffffff; margin-top: 2px;">
                    {{ $barang ? $barang->formatStokBerantai((int) $record->stok) : $record->stok }}
                </div>
            </div>

            <div style="background: #111113; border: 1px solid #27272a; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 10px; font-weight: 700; color: #a1a1aa; text-transform: uppercase;">Batas Minimum</div>
                <div style="font-size: 14px; font-weight: 700; color: #ffffff; margin-top: 2px;">
                    {{ $barang ? $barang->formatStokBerantai((int) $record->stok_minimum) : $record->stok_minimum }}
                </div>
            </div>

            <div style="background: #111113; border: 1px solid #27272a; border-radius: 6px; padding: 10px 12px;">
                <div style="font-size: 10px; font-weight: 700; color: #a1a1aa; text-transform: uppercase;">Defisit</div>
                <div style="font-size: 14px; font-weight: 700; color: {{ $record->defisit > 0 ? '#f87171' : '#4ade80' }}; margin-top: 2px;">
                    {{ $record->defisit > 0 ? ($barang ? $barang->formatStokBerantai($record->defisit) : $record->defisit) : 'Aman' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel 1: Distribusi Stok Barang Ini di Seluruh Gudang --}}
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h4 style="font-size: 13px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">
                Distribusi Stok Seluruh Gudang
            </h4>
            <span style="font-size: 11px; color: #a1a1aa;">Periksa sumber stok untuk rencana mutasi</span>
        </div>

        <div class="kontrol-stok-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="width: 32%;">Lokasi Gudang</th>
                        <th style="width: 24%; text-align: center;">Sisa Stok</th>
                        <th style="width: 20%; text-align: center;">Batas Minimum</th>
                        <th style="width: 24%; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allGudangStocks as $bg)
                        @php
                            $isCurrent = $bg->gudang_id === $record->gudang_id;
                            $rowBg = $isCurrent ? '#202024' : '#18181b';
                            $stokFormatted = $barang ? $barang->formatStokBerantai((int) $bg->stok) : $bg->stok;
                            $minFormatted = $barang ? $barang->formatStokBerantai((int) $bg->stok_minimum) : $bg->stok_minimum;
                        @endphp
                        <tr style="background: {{ $rowBg }}; border-left: {{ $isCurrent ? '3px solid #38bdf8' : 'none' }};">
                            <td style="font-weight: 600; color: #ffffff;">
                                {{ $bg->gudang?->nama_gudang ?? '-' }}
                                @if($isCurrent)
                                    <span style="display: inline-block; margin-left: 6px; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #0369a1; color: #ffffff;">
                                        Gudang Terpilih
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center; font-weight: 700; color: {{ $bg->stok <= 0 ? '#f87171' : ($bg->status_stok === 'menipis' ? '#fdba74' : '#86efac') }};">
                                {{ $stokFormatted }}
                            </td>
                            <td style="text-align: center; color: #a1a1aa;">
                                {{ $minFormatted }}
                            </td>
                            <td style="text-align: center;">
                                @if($bg->status_stok === 'habis')
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #450a0a; color: #fecaca; border: 1px solid #991b1b;">
                                        Habis
                                    </span>
                                @elseif($bg->status_stok === 'menipis')
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #451a03; color: #fed7aa; border: 1px solid #c2410c;">
                                        Menipis (-{{ $barang ? $barang->formatStokBerantai($bg->defisit) : $bg->defisit }})
                                    </span>
                                @else
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #052e16; color: #bbf7d0; border: 1px solid #166534;">
                                        Aman
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td style="padding: 12px 14px;">Total Seluruh Toko (Global)</td>
                        <td style="padding: 12px 14px; text-align: center;">
                            {{ $barang ? $barang->formatStokBerantai((int) $totalStokAll) : $totalStokAll }}
                        </td>
                        <td style="padding: 12px 14px; text-align: center; color: #d4d4d8;">
                            {{ $barang ? $barang->formatStokBerantai((int) $totalMinAll) : $totalMinAll }}
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            @if($totalStokAll <= $barang?->stok_minimum && ($barang?->stok_minimum ?? 0) > 0)
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #451a03; color: #fed7aa; border: 1px solid #c2410c;">
                                    Toko Perlu Restok
                                </span>
                            @else
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #052e16; color: #bbf7d0; border: 1px solid #166534;">
                                    Stok Global Aman
                                </span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Tabel 2: 5 Riwayat Kartu Stok Terakhir di Gudang Terkait --}}
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h4 style="font-size: 13px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">
                Riwayat Mutasi Terakhir ({{ $gudang?->nama_gudang }})
            </h4>
            <span style="font-size: 11px; color: #a1a1aa;">5 mutasi terakhir</span>
        </div>

        <div class="kontrol-stok-table-wrapper" style="max-height: 240px; margin-bottom: 0;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="width: 18%;">Tanggal</th>
                        <th style="width: 22%;">No. Bukti</th>
                        <th style="width: 26%;">Keterangan</th>
                        <th style="width: 11%; text-align: center;">Masuk</th>
                        <th style="width: 11%; text-align: center;">Keluar</th>
                        <th style="width: 12%; text-align: right;">Sisa Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentKartuStok as $ks)
                        @php
                            $rowBg = ($loop->index % 2 === 0) ? '#18181b' : '#111113';
                            $isMasuk = in_array($ks->jenis_transaksi, ['masuk', 'pindah_masuk']);
                            $isKeluar = in_array($ks->jenis_transaksi, ['keluar', 'pindah_keluar']);
                            $masukFormatted = $isMasuk ? ($barang ? $barang->formatStokBerantai((int) $ks->jumlah) : (string) $ks->jumlah) : '-';
                            $keluarFormatted = $isKeluar ? ($barang ? $barang->formatStokBerantai((int) $ks->jumlah) : (string) $ks->jumlah) : '-';
                            $sisaFormatted = $barang ? $barang->formatStokBerantai((int) ($ks->saldo ?? 0)) : (string) ($ks->saldo ?? 0);
                        @endphp
                        <tr style="background: {{ $rowBg }};">
                            <td style="color: #d4d4d8; font-size: 11.5px;">
                                {{ \Carbon\Carbon::parse($ks->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="color: #e4e4e7; font-weight: 500; font-size: 11.5px;">
                                {{ $ks->nomer_entry ?? $ks->nomer_bukti ?? '-' }}
                            </td>
                            <td style="color: #a1a1aa; font-size: 11.5px;">
                                {{ $ks->keterangan ?? '-' }}
                            </td>
                            <td style="text-align: center; color: #4ade80; font-weight: 600; font-size: 11.5px;">
                                {{ $masukFormatted }}
                            </td>
                            <td style="text-align: center; color: #f87171; font-weight: 600; font-size: 11.5px;">
                                {{ $keluarFormatted }}
                            </td>
                            <td style="text-align: right; color: #ffffff; font-weight: 700; font-size: 11.5px;">
                                {{ $sisaFormatted }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: #71717a; padding: 20px;">
                                Belum ada riwayat mutasi kartu stok untuk barang ini di gudang terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
