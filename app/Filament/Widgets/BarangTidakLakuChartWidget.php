<?php

namespace App\Filament\Widgets;

use App\Models\DetailJual;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class BarangTidakLakuChartWidget extends ChartWidget
{
    protected ?string $heading = '5 Barang Paling Jarang Laku (Slow Moving)';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public ?string $filter = 'all';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Semua Waktu',
            'month' => 'Bulan Ini',
        ];
    }

    public function getDescription(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $url = \App\Filament\Pages\RingkasanBarang::getUrl();

        return new HtmlString(
            '<a href="' . e($url) . '" style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;text-decoration:underline;margin-top:2px;" title="Lihat semua data barang">'
            . 'Lihat Lengkap &rarr;'
            . '</a>'
        );
    }

    protected function getData(): array
    {
        $filter = $this->filter;
        $query = DetailJual::query()
            ->join('barang', 'detail_jual.barang_id', '=', 'barang.id')
            ->join('penjualan', 'detail_jual.penjualan_id', '=', 'penjualan.id');

        if ($filter === 'month') {
            $query->whereBetween('penjualan.tanggal', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        $slowItems = $query->select(
                'barang.id',
                'barang.nama_barang',
                'barang.satuan',
                'barang.harga_jual',
                DB::raw('SUM(detail_jual.jumlah) as total_qty'),
                DB::raw('MAX(penjualan.tanggal) as terakhir_jual'),
                DB::raw('(SELECT COALESCE(SUM(bg.stok), 0) FROM barang_gudang bg WHERE bg.barang_id = barang.id) as stok_sekarang')
            )
            ->groupBy('barang.id', 'barang.nama_barang', 'barang.satuan', 'barang.harga_jual')
            ->orderBy('total_qty', 'asc')
            ->take(5)
            ->get();

        $labels = $slowItems->pluck('nama_barang')->toArray();
        $data = $slowItems->pluck('total_qty')->map(fn ($v) => (int) $v)->toArray();
        $satuanData = $slowItems->pluck('satuan')->map(fn ($v) => $v ?? 'Pcs')->toArray();
        $stokData = $slowItems->pluck('stok_sekarang')->map(fn ($v) => (int) $v)->toArray();
        $hargaData = $slowItems->pluck('harga_jual')->map(fn ($v) => (int) $v)->toArray();
        $terakhirJualData = $slowItems->map(function ($item) {
            if (empty($item->terakhir_jual)) {
                return 'Belum pernah';
            }
            $date = Carbon::parse($item->terakhir_jual);
            $diff = (int) $date->diffInDays(now());
            $formatted = $date->translatedFormat('d M Y');
            if ($diff === 0) {
                return $formatted . ' (hari ini)';
            }
            return $formatted . ' (' . $diff . ' hari lalu)';
        })->toArray();

        if (empty($labels)) {
            $labels = ['Belum ada transaksi'];
            $data = [0];
            $satuanData = [''];
            $stokData = [0];
            $hargaData = [0];
            $terakhirJualData = ['-'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Terjual',
                    'data' => $data,
                    'satuan' => $satuanData,
                    'stok' => $stokData,
                    'harga' => $hargaData,
                    'terakhir_jual' => $terakhirJualData,
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.85)',
                        'rgba(249, 115, 22, 0.85)',
                        'rgba(234, 179, 8, 0.85)',
                        'rgba(156, 163, 175, 0.85)',
                        'rgba(107, 114, 128, 0.85)',
                    ],
                    'borderColor' => [
                        '#ef4444',
                        '#f97316',
                        '#eab308',
                        '#9ca3af',
                        '#6b7280',
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
                indexAxis: 'y',
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                animations: {
                    x: {
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
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        padding: 12,
                        callbacks: {
                            title: function(tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            label: function(context) {
                                var ds = context.dataset;
                                var idx = context.dataIndex;
                                var sat = ds.satuan[idx] || 'Pcs';
                                return 'Terjual: ' + context.raw + ' ' + sat;
                            },
                            afterBody: function(tooltipItems) {
                                var idx = tooltipItems[0].dataIndex;
                                var ds = tooltipItems[0].dataset;
                                var sat = ds.satuan[idx] || 'Pcs';
                                return [
                                    '',
                                    'Stok: ' + ds.stok[idx] + ' ' + sat,
                                    'Terakhir Jual: ' + ds.terakhir_jual[idx],
                                    'Harga: Rp ' + Number(ds.harga[idx]).toLocaleString('id-ID')
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
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
