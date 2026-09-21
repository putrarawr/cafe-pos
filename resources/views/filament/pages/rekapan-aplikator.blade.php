<x-filament-panels::page>
    @php
        $stats = $this->overviewStats;
    @endphp

    <style>
        /* Spacing & Padding Luas pada Tabel (Standar AGENTS.md) */
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

        /* Styling Bersih & Netral Baris Rangkuman pada Tabel */
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

        /* Label 'Rangkuman' dengan Tampilan Netral Bersih */
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

        /* Teks Nilai Angka Rangkuman */
        .fi-ta-table tfoot td:not(:first-child),
        .fi-ta-table tr[class*="summary"] td:not(:first-child),
        .fi-ta-table tr:has([class*="summary"]) td:not(:first-child) {
            font-weight: 700 !important;
            color: #ffffff !important;
        }
    </style>

    <div style="display: flex; flex-direction: column; gap: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        {{-- 1. 4 Kartu Metrik Finansial Tetap (Posisi Teratas) --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
            {{-- Kartu 1: Total Pesanan Delivery --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="min-height: 22px; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Total Pesanan Delivery</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['total_qty'], 0, ',', '.') }} ITEM
                    </span>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    {{ number_format($stats['count_transaksi'], 0, ',', '.') }} <span style="font-size: 14px; font-weight: 500; color: #a1a1aa;">Transaksi</span>
                </div>
            </div>

            {{-- Kartu 2: Omset Kotor (Gross) --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="min-height: 22px; display: flex; align-items: center;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Omset Kotor (Gross)</span>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_omset'], 0, ',', '.') }}
                </div>
            </div>

            {{-- Kartu 3: Potongan Komisi Platform --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="min-height: 22px; display: flex; align-items: center;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Potongan Komisi Platform</span>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #fbbf24; margin-top: 12px; letter-spacing: -0.02em;">
                    - Rp {{ number_format($stats['sum_komisi'], 0, ',', '.') }} <span style="font-size: 14px; font-weight: 600; color: #f59e0b; margin-left: 4px;">({{ $stats['komisi_persen'] }}%)</span>
                </div>
            </div>

            {{-- Kartu 4: Pendapatan Bersih (Net) --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="min-height: 22px; display: flex; align-items: center;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Pendapatan Bersih (Net)</span>
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #34d399; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_bersih'], 0, ',', '.') }} <span style="font-size: 14px; font-weight: 600; color: #10b981; margin-left: 4px;">({{ $stats['margin_bersih_persen'] }}%)</span>
                </div>
            </div>
        </div>

        {{-- Indikator Jika Sedang Terfilter Spesifik --}}
        @if ($stats['is_filtered'])
            <div style="display: flex; align-items: center; justify-content: space-between; background: #202024; border: 1px solid #3f3f46; border-radius: 6px; padding: 10px 16px; font-size: 12px; color: #d4d4d8;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #60a5fa;"></span>
                    <span>Menampilkan ringkasan terfilter: <strong style="color: #60a5fa; font-size: 13px;">{{ $stats['filtered_aplikator_nama'] }}</strong></span>
                </div>
                <button type="button" wire:click="hapusFilterAplikator()" style="background: #27272a; color: #e4e4e7; border: 1px solid #52525b; padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; transition: background 0.15s ease;">
                    Reset Filter (Tampilkan Semua)
                </button>
            </div>
        @endif

        {{-- 2. Form Filter Periode & Aplikator (Di Bawah 4 Kartu) --}}
        <div>
            {{ $this->form }}
        </div>

        {{-- 3. Tabel Agregasi Rekapan per Aplikator (Posisi Bawah) --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
