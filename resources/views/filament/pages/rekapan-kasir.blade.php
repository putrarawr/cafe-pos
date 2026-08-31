<x-filament-panels::page>
    @php
        $stats = $this->overviewStats;
    @endphp

    <style>
        /* Spacing & Padding Luas pada Tabel */
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

    <div style="display: flex; flex-direction: column; gap: 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        {{-- 4 Kartu Ringkasan Metrik Setoran Toko (Gaya Bersih & Konsisten) --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
            {{-- Tunai --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Tunai</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['count_tunai'], 0, ',', '.') }} TRANSAKSI
                    </span>
                </div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_tunai'], 0, ',', '.') }}
                </div>
            </div>

            {{-- QRIS --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">QRIS</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['count_qris'], 0, ',', '.') }} TRANSAKSI
                    </span>
                </div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_qris'], 0, ',', '.') }}
                </div>
            </div>

            {{-- Bank / Transfer --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Bank / Transfer</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['count_transfer'], 0, ',', '.') }} TRANSAKSI
                    </span>
                </div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_transfer'], 0, ',', '.') }}
                </div>
            </div>

            {{-- Total Keseluruhan --}}
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">Total Keseluruhan</span>
                    <span style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700;">
                        {{ number_format($stats['count_transaksi'], 0, ',', '.') }} TRANSAKSI
                    </span>
                </div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; margin-top: 12px; letter-spacing: -0.02em;">
                    Rp {{ number_format($stats['sum_omset'], 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Form Filter --}}
        <div>
            {{ $this->form }}
        </div>

        {{-- Tabel Agregasi Rekapan Kasir --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
