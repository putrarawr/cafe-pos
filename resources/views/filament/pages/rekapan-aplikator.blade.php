<x-filament-panels::page>
    @php
        $stats = $this->overviewStats;
        $activeAplikatorId = (int) ($this->data['aplikator_id'] ?? 0);
    @endphp

    <style>
        .fi-ta-table th {
            padding-top: 14px !important;
            padding-bottom: 14px !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }

        .fi-ta-table tbody tr td {
            padding-top: 16px !important;
            padding-bottom: 16px !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }

        .fi-ta-table tfoot,
        .fi-ta-table tfoot tr,
        .fi-ta-table tr[class*="summary"],
        .fi-ta-table tr:has([class*="summary"]) {
            background-color: #18181b !important;
        }

        .fi-ta-table tfoot tr td,
        .fi-ta-table tr[class*="summary"] td,
        .fi-ta-table tr:has([class*="summary"]) td {
            background-color: #18181b !important;
            border-top: 2px solid #3f3f46 !important;
            border-bottom: 1px solid #27272a !important;
            padding-top: 16px !important;
            padding-bottom: 16px !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }

        .fi-ta-table tfoot td:first-child span,
        .fi-ta-table tr[class*="summary"] td:first-child span,
        .fi-ta-table tr:has([class*="summary"]) td:first-child span {
            display: inline-block !important;
            background: #27272a !important;
            color: #e4e4e7 !important;
            border: 1px solid #3f3f46 !important;
            border-radius: 4px !important;
            padding: 4px 10px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }

        .fi-ta-table tfoot td:not(:first-child),
        .fi-ta-table tr[class*="summary"] td:not(:first-child),
        .fi-ta-table tr:has([class*="summary"]) td:not(:first-child) {
            font-weight: 700 !important;
            color: #ffffff !important;
        }
    </style>

    <div style="display: flex; flex-direction: column; gap: 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        {{-- Kartu Ringkasan per Aplikator --}}
        <div style="font-size: 11px; color: #71717a; margin-bottom: -14px;">
            Klik kartu aplikator untuk memfilter tabel. Klik &ldquo;Total Keseluruhan&rdquo; untuk menampilkan semua aplikator.
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
            @foreach ($stats['aplikators'] as $apl)
                @php
                    $adaTransaksi = $apl['count_transaksi'] > 0;
                    $isActive = ((int) $apl['id']) === $activeAplikatorId;
                @endphp
                <div
                    wire:click="pilihAplikator({{ $apl['id'] }})"
                    role="button"
                    tabindex="0"
                    title="Klik untuk memfilter &mdash; {{ $apl['nama_aplikator'] }}"
                    style="background: #18181b; border: 1px solid {{ $isActive ? '#34d399' : ($adaTransaksi ? '#3f3f46' : '#27272a') }}; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); cursor: pointer; transition: border-color 0.15s ease, opacity 0.15s ease; {{ (!$isActive && !$adaTransaksi) ? 'opacity: 0.55;' : '' }}"
                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 11px; font-weight: 700; color: {{ $isActive ? '#34d399' : '#a1a1aa' }}; text-transform: uppercase; letter-spacing: 0.05em;">{{ $apl['nama_aplikator'] }}</span>
                        <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                            {{ number_format($apl['count_transaksi'], 0, ',', '.') }} TRANSAKSI
                        </span>
                    </div>
                    <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                        Rp {{ number_format($apl['sum_omset'], 0, ',', '.') }}
                    </div>
                    @if ($adaTransaksi)
                        <div style="font-size: 11px; color: #fbbf24; margin-top: 6px;">
                            Komisi: -Rp {{ number_format($apl['sum_komisi'], 0, ',', '.') }}
                        </div>
                        <div style="font-size: 11px; color: #a1a1aa; margin-top: 4px;">
                            Kirim: -Rp {{ number_format($apl['sum_kirim'], 0, ',', '.') }}
                            &bull; Bersih: <span style="color: #34d399;">Rp {{ number_format($apl['sum_omset'] - $apl['sum_kirim'] - $apl['sum_komisi'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Total Keseluruhan --}}
            <div
                wire:click="hapusFilterAplikator()"
                role="button"
                tabindex="0"
                title="Klik untuk menampilkan semua aplikator"
                style="background: #18181b; border: 1px solid {{ $activeAplikatorId === 0 ? '#60a5fa' : '#3f3f46' }}; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); cursor: pointer; transition: border-color 0.15s ease;"
                aria-pressed="{{ $activeAplikatorId === 0 ? 'true' : 'false' }}"
            >
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: {{ $activeAplikatorId === 0 ? '#60a5fa' : '#a1a1aa' }}; text-transform: uppercase; letter-spacing: 0.05em;">{{ $activeAplikatorId === 0 ? 'Total Semua Aplikator' : 'Total Terfilter' }}</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['count_transaksi'], 0, ',', '.') }} TRANSAKSI
                    </span>
                </div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_omset'], 0, ',', '.') }}
                </div>
                <div style="font-size: 11px; color: #a1a1aa; margin-top: 6px;">
                    Komisi <span style="color: #fbbf24;">-Rp {{ number_format($stats['sum_komisi'], 0, ',', '.') }}</span>
                    &bull; Kirim <span style="color: #a1a1aa;">-Rp {{ number_format($stats['sum_kirim'], 0, ',', '.') }}</span>
                    &bull; Bersih <span style="color: #34d399;">Rp {{ number_format($stats['sum_bersih'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Form Filter --}}
        <div>
            {{ $this->form }}
        </div>

        {{-- Tabel Agregasi Rekapan Aplikator --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
