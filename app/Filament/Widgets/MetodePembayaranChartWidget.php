<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MetodePembayaranChartWidget extends ChartWidget
{
    protected ?string $heading = 'Komposisi Metode Pembayaran';

    protected static ?int $sort = 4;

    public ?string $filter = 'all';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Semua Transaksi',
            'month' => 'Bulan Ini',
        ];
    }

    protected function getData(): array
    {
        $filter = $this->filter;
        $query = Penjualan::query();

        if ($filter === 'month') {
            $query->whereBetween('tanggal', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        $pembayaran = $query->select('jenis_pembayaran', DB::raw('COUNT(*) as total_transaksi'), DB::raw('SUM(neto) as total_nominal'))
            ->groupBy('jenis_pembayaran')
            ->pluck('total_nominal', 'jenis_pembayaran')
            ->toArray();

        $tunai = (int) ($pembayaran['tunai'] ?? 0);
        $qris = (int) ($pembayaran['qris'] ?? 0);
        $transfer = (int) ($pembayaran['transfer'] ?? 0);

        return [
            'datasets' => [
                [
                    'label' => 'Total Transaksi (Rp)',
                    'data' => [$tunai, $qris, $transfer],
                    'backgroundColor' => [
                        '#10b981', // Tunai (Green)
                        '#f59e0b', // QRIS (Amber)
                        '#0ea5e9', // Transfer (Sky)
                    ],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => ['Tunai', 'QRIS', 'Transfer'],
        ];
    }

    protected function getOptions(): RawJs|array
    {
        return RawJs::make(<<<JS
            {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) { return context.label + ': Rp ' + Number(context.raw).toLocaleString('id-ID'); }
                        }
                    }
                },
                cutout: '65%'
            }
        JS);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
