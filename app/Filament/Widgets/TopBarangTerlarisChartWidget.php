<?php

namespace App\Filament\Widgets;

use App\Models\DetailJual;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopBarangTerlarisChartWidget extends ChartWidget
{
    protected ?string $heading = '5 Barang Terlaris (Top Selling)';

    protected static ?int $sort = 5;

    public ?string $filter = 'all';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Semua Waktu',
            'month' => 'Bulan Ini',
        ];
    }

    protected function getData(): array
    {
        $filter = $this->filter;
        $query = DetailJual::query()
            ->join('barang', 'detail_jual.barang_id', '=', 'barang.id');

        if ($filter === 'month') {
            $query->whereHas('penjualan', function ($q) {
                $q->whereBetween('tanggal', [now()->startOfMonth(), now()->endOfMonth()]);
            });
        }

        $topItems = $query->select('barang.nama_barang', DB::raw('SUM(detail_jual.jumlah) as total_qty'))
            ->groupBy('barang.id', 'barang.nama_barang')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        $labels = $topItems->pluck('nama_barang')->toArray();
        $data = $topItems->pluck('total_qty')->map(fn ($v) => (int) $v)->toArray();

        if (empty($labels)) {
            $labels = ['Belum ada transaksi'];
            $data = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Terjual (Item)',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(16, 185, 129, 0.85)', // Emerald
                        'rgba(99, 102, 241, 0.85)', // Indigo
                        'rgba(245, 158, 11, 0.85)', // Amber
                        'rgba(236, 72, 153, 0.85)', // Pink
                        'rgba(14, 165, 233, 0.85)', // Sky
                    ],
                    'borderColor' => [
                        '#10b981',
                        '#6366f1',
                        '#f59e0b',
                        '#ec4899',
                        '#0ea5e9',
                    ],
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs|array
    {
        return RawJs::make(<<<JS
            {
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                animations: {
                    y: {
                        duration: 2000,
                        from: 0
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) { return 'Terjual: ' + context.raw + ' item'; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
