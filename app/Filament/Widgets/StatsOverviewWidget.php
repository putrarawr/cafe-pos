<?php

namespace App\Filament\Widgets;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 2,
            'xl' => 4,
        ];
    }

    private function formatRp(float|int $amount): HtmlString
    {
        $plain = 'Rp ' . number_format($amount, 0, ',', '.');
        $formatted = 'Rp&nbsp;' . number_format($amount, 0, ',', '.');

        return new HtmlString('<span style="white-space: nowrap !important; font-size: clamp(0.95rem, 1.35vw, 1.25rem) !important; line-height: 1.4 !important; max-width: 100% !important; overflow: hidden !important; text-overflow: ellipsis !important; display: block !important; font-weight: 800 !important;" title="' . e($plain) . '">' . $formatted . '</span>');
    }

    private function formatItemCount(int $count): HtmlString
    {
        return new HtmlString('<span style="white-space: nowrap !important; font-size: clamp(0.95rem, 1.35vw, 1.25rem) !important; line-height: 1.4 !important; max-width: 100% !important; overflow: hidden !important; text-overflow: ellipsis !important; display: block !important; font-weight: 800 !important;">' . $count . '&nbsp;item</span>');
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Penjualan Hari Ini
        $penjualanHariIni = Penjualan::whereDate('tanggal', $today)->get();
        $totalHariIni = $penjualanHariIni->sum('neto');
        $countHariIni = $penjualanHariIni->count();

        // Penjualan Bulan Ini
        $totalBulanIni = Penjualan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('neto');

        // Pembelian Bulan Ini
        $totalBeliBulanIni = Pembelian::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('neto');

        // Total Barang Unik dengan Stok <= 5 (Low Stock)
        $lowStockCount = Barang::whereHas('gudangs', function ($q) {
            $q->where('barang_gudang.stok', '<=', 5);
        })->count();

        // Trend 7 hari terakhir untuk sparkline animasi
        $penjualanTrend = collect();
        $pembelianTrend = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $penjualanTrend->push((int) Penjualan::whereDate('tanggal', $d)->sum('neto'));
            $pembelianTrend->push((int) Pembelian::whereDate('tanggal', $d)->sum('neto'));
        }

        return [
            Stat::make('Penjualan Hari Ini', $this->formatRp($totalHariIni))
                ->description("{$countHariIni} transaksi sukses")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->chart($penjualanTrend->toArray())
                ->color('success'),

            Stat::make('Penjualan Bulan Ini', $this->formatRp($totalBulanIni))
                ->description(Carbon::now()->translatedFormat('F Y'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->chart($penjualanTrend->toArray())
                ->color('primary'),

            Stat::make('Pembelian Bulan Ini', $this->formatRp($totalBeliBulanIni))
                ->description('Total modal barang masuk')
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->chart($pembelianTrend->toArray())
                ->color('warning'),

            Stat::make('Stok Menipis (<= 5)', $this->formatItemCount($lowStockCount))
                ->description($lowStockCount > 0 ? 'Klik untuk lihat rincian' : 'Semua stok aman')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($lowStockCount > 0 ? 'danger' : 'gray')
                ->extraAttributes($lowStockCount > 0 ? [
                    'onclick' => 'window.__showLowStockAlert && window.__showLowStockAlert()',
                    'style' => 'cursor:pointer',
                ] : []),
        ];
    }
}
