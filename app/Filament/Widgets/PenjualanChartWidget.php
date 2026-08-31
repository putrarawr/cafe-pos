<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PenjualanChartWidget extends ChartWidget
{
    protected ?string $heading = 'Grafik OMSET Penjualan';

    protected static ?int $sort = 2;

    public ?string $filter = '7';

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 Hari Terakhir',
            '30' => '30 Hari Terakhir',
            'month' => 'Bulan Ini',
        ];
    }

    protected function getData(): array
    {
        $dates = collect();
        $totals = collect();
        $filter = $this->filter;

        if ($filter === '30') {
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dates->push($date->format('d M'));
                $sum = Penjualan::whereDate('tanggal', $date)->sum('neto');
                $totals->push($sum);
            }
        } elseif ($filter === 'month') {
            $daysInMonth = Carbon::now()->daysInMonth;
            $startOfMonth = Carbon::now()->startOfMonth();
            for ($i = 0; $i < $daysInMonth; $i++) {
                $date = (clone $startOfMonth)->addDays($i);
                if ($date->isFuture()) {
                    break;
                }
                $dates->push($date->format('d M'));
                $sum = Penjualan::whereDate('tanggal', $date)->sum('neto');
                $totals->push($sum);
            }
        } else {
            // Default 7 hari
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dates->push($date->format('d M'));
                $sum = Penjualan::whereDate('tanggal', $date)->sum('neto');
                $totals->push($sum);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Omset Penjualan (Rp)',
                    'data' => $totals->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 3,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 7,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor' => '#ffffff',
                ],
            ],
            'labels' => $dates->toArray(),
        ];
    }

    protected function getOptions(): RawJs|array
    {
        return RawJs::make(<<<JS
            {
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                animations: {
                    y: {
                        duration: 2000,
                        from: 0
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'Rp ' + Number(value).toLocaleString('id-ID'); }
                        }
                    }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
