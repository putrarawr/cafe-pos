<?php

namespace App\Filament\Resources\Pembelians;

use App\Filament\Resources\Pembelians\Pages\CreatePembelian;
use App\Filament\Resources\Pembelians\Pages\EditPembelian;
use App\Filament\Resources\Pembelians\Pages\ListPembelians;
use App\Filament\Resources\Pembelians\Schemas\PembelianForm;
use App\Filament\Resources\Pembelians\Tables\PembeliansTable;
use App\Models\Pembelian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PembelianResource extends Resource
{
    protected static ?string $model = Pembelian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Barang Masuk (Pembelian)';

    protected static ?string $recordTitleAttribute = 'nomer_entry';

    protected static ?string $modelLabel = 'Pembelian';

    protected static ?string $pluralModelLabel = 'Pembelian';

    public static function form(Schema $schema): Schema
    {
        return PembelianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PembeliansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembelians::route('/'),
            'create' => CreatePembelian::route('/create'),
            'edit' => EditPembelian::route('/{record}/edit'),
        ];
    }
}
