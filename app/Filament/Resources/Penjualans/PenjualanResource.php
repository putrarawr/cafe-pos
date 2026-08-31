<?php

namespace App\Filament\Resources\Penjualans;

use App\Filament\Resources\Penjualans\Pages\ListPenjualans;
use App\Filament\Resources\Penjualans\Tables\PenjualansTable;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PenjualanResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Barang Keluar (Penjualan)';

    protected static ?string $recordTitleAttribute = 'nomer_nota';

    protected static ?string $modelLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return PenjualansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenjualans::route('/'),
        ];
    }
}
