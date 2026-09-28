<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\DetailJual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class RingkasanBarang extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Ringkasan Penjualan Barang';

    protected static ?string $title = 'Ringkasan Penjualan & Stok Barang';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.ringkasan-barang';

    public string $sortMode = 'slow';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sort_slow')
                ->label('Jarang Laku')
                ->icon('heroicon-o-arrow-trending-down')
                ->color(fn () => $this->sortMode === 'slow' ? 'danger' : 'gray')
                ->action(function () {
                    $this->sortMode = 'slow';
                    $this->resetTable();
                }),
            Action::make('sort_fast')
                ->label('Paling Laris')
                ->icon('heroicon-o-arrow-trending-up')
                ->color(fn () => $this->sortMode === 'fast' ? 'success' : 'gray')
                ->action(function () {
                    $this->sortMode = 'fast';
                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $subQuery = DetailJual::query()
            ->join('penjualan', 'detail_jual.penjualan_id', '=', 'penjualan.id')
            ->select(
                'detail_jual.barang_id',
                DB::raw('SUM(detail_jual.jumlah) as total_terjual'),
                DB::raw('MAX(penjualan.tanggal) as terakhir_jual')
            )
            ->groupBy('detail_jual.barang_id');

        $query = Barang::query()
            ->leftJoinSub($subQuery, 'penjualan_stats', function ($join) {
                $join->on('barang.id', '=', 'penjualan_stats.barang_id');
            })
            ->leftJoin('barang_gudang', 'barang.id', '=', 'barang_gudang.barang_id')
            ->select(
                'barang.id',
                'barang.nama_barang',
                'barang.satuan',
                'barang.harga_jual',
                DB::raw('COALESCE(penjualan_stats.total_terjual, 0) as total_terjual'),
                DB::raw('penjualan_stats.terakhir_jual as terakhir_jual'),
                DB::raw('COALESCE(SUM(barang_gudang.stok), 0) as stok_sekarang')
            )
            ->groupBy(
                'barang.id',
                'barang.nama_barang',
                'barang.satuan',
                'barang.harga_jual',
                'penjualan_stats.total_terjual',
                'penjualan_stats.terakhir_jual'
            );

        if ($this->sortMode === 'fast') {
            $query->orderByDesc('total_terjual');
        } else {
            $query->orderBy('total_terjual', 'asc');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->width('50px'),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('total_terjual')
                    ->label('Total Terjual')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, $record) => ((int) $state) . ' ' . ($record->satuan ?? 'Pcs'))
                    ->color(fn ($state) => match (true) {
                        (int) $state === 0 => 'danger',
                        (int) $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->badge(),

                TextColumn::make('terakhir_jual')
                    ->label('Terakhir Terjual')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Belum pernah';
                        }
                        $date = \Carbon\Carbon::parse($state);
                        $diff = (int) $date->diffInDays(now());
                        $formatted = $date->translatedFormat('d M Y');

                        if ($diff === 0) {
                            return $formatted . ' (hari ini)';
                        }

                        return $formatted . ' (' . $diff . ' hari lalu)';
                    })
                    ->color(fn ($state) => match (true) {
                        empty($state) => 'danger',
                        ((int) \Carbon\Carbon::parse($state)->diffInDays(now())) > 30 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('stok_sekarang')
                    ->label('Stok Saat Ini')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, $record) => ((int) $state) . ' ' . ($record->satuan ?? 'Pcs'))
                    ->color(fn ($state) => match (true) {
                        (int) $state === 0 => 'danger',
                        (int) $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->badge(),

                TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((int) $state, 0, ',', '.'))
                    ->color('gray'),
            ])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Tidak ada data barang')
            ->emptyStateDescription('Belum ada barang yang tercatat di sistem.');
    }
}
