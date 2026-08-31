<?php

namespace App\Filament\Resources\LaporanPenjualanDetails\Widgets;

use App\Filament\Resources\LaporanPenjualanDetails\Pages\ListLaporanPenjualanDetails;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanDetailOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListLaporanPenjualanDetails::class;
    }

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        // Ambil query data yang sedang difilter/dicari di tabel (hapus order by agar aman untuk PostgreSQL aggregate)
        $query = (clone $this->getPageTableQuery())->reorder();

        // 1. Total Penjualan (Subtotal)
        $totalPenjualan = (float) (clone $query)->sum('subtotal');

        // 2. Total Laba Bersih
        // Rumus: subtotal - ((jumlah * coalesce(hpp, 0)) + (coalesce(bonus_qty, 0) * coalesce(bonus_hpp, 0)))
        $labaResult = (clone $query)
            ->selectRaw('COALESCE(SUM(subtotal - ((jumlah * COALESCE(hpp, 0)) + (COALESCE(bonus_qty, 0) * COALESCE(bonus_hpp, 0)))), 0) as total_laba')
            ->first();
        $totalLabaBersih = (float) ($labaResult->total_laba ?? 0);

        // 3. Total Kuantitas Terjual & Total Baris Item
        $totalQty = (int) (clone $query)->sum('jumlah');
        $totalBaris = (int) (clone $query)->count();

        // 4. Total Transaksi yang Mendapat Bonus
        $totalBonusQty = (int) (clone $query)->whereNotNull('bonus_barang_id')->where('bonus_qty', '>', 0)->sum('bonus_qty');
        $itemBerbonusCount = (int) (clone $query)->whereNotNull('bonus_barang_id')->where('bonus_qty', '>', 0)->count();

        return [
            Stat::make('Total Penjualan', 'Rp ' . number_format($totalPenjualan, 0, ',', '.'))
                ->description("Dari {$totalBaris} item transaksi")
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),

            Stat::make('Total Laba Bersih', 'Rp ' . number_format($totalLabaBersih, 0, ',', '.'))
                ->description($totalLabaBersih >= 0 ? 'Margin profit positif' : 'Margin profit negatif')
                ->descriptionIcon($totalLabaBersih >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->color($totalLabaBersih >= 0 ? 'success' : 'danger'),

            Stat::make('Total Item Terjual', number_format($totalQty, 0, ',', '.') . ' Qty')
                ->description("{$totalBaris} baris produk")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('info'),

            Stat::make('Bonus Diberikan', number_format($totalBonusQty, 0, ',', '.') . ' Qty')
                ->description("{$itemBerbonusCount} item berbonus")
                ->descriptionIcon(Heroicon::OutlinedGift)
                ->color('warning'),
        ];
    }
}
