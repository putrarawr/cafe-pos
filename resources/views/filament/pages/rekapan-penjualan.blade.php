<x-filament-panels::page>
    <style>
        /* Spacing & Padding Luas pada Tabel Rekapan Penjualan */
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

        .fi-ta-table tfoot tr td {
            padding-top: 18px !important;
            padding-bottom: 18px !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
    </style>

    <div style="display: flex; flex-direction: column; gap: 28px;">
        <div>
            {{ $this->form }}
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
