<?php

namespace App\Filament\Resources\LaporanPenjualanDetails;

use App\Filament\Resources\LaporanPenjualanDetails\Pages\ListLaporanPenjualanDetails;
use App\Filament\Resources\LaporanPenjualanDetails\Tables\LaporanPenjualanDetailsTable;
use App\Models\DetailJual;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LaporanPenjualanDetailResource extends Resource
{
    protected static ?string $model = DetailJual::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan Penjualan Item & Bonus';

    protected static ?string $modelLabel = 'Laporan Penjualan Item & Bonus';

    protected static ?string $pluralModelLabel = 'Laporan Penjualan Item & Bonus';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return LaporanPenjualanDetailsTable::configure($table);
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\LaporanPenjualanDetails\Widgets\LaporanPenjualanDetailOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanPenjualanDetails::route('/'),
        ];
    }
}
