<x-filament-panels::page>
    <style>
        /* Spacing & Padding Luas pada Tabel Sesuai Panduan AGENTS.md */
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
    </style>

    <div style="display: flex; flex-direction: column; gap: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        {{-- Form Filter Interaktif Atas --}}
        <div>
            {{ $this->form }}
        </div>

        {{-- Tabel Kontrol Stok Utama --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
