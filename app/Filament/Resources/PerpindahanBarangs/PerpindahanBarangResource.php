<?php

namespace App\Filament\Resources\PerpindahanBarangs;

use App\Filament\Resources\PerpindahanBarangs\Pages\CreatePerpindahanBarang;

use App\Filament\Resources\PerpindahanBarangs\Pages\ListPerpindahanBarangs;
use App\Filament\Resources\PerpindahanBarangs\Schemas\PerpindahanBarangForm;
use App\Filament\Resources\PerpindahanBarangs\Tables\PerpindahanBarangsTable;
use App\Models\PerpindahanBarang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerpindahanBarangResource extends Resource
{
    protected static ?string $model = PerpindahanBarang::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?string $navigationLabel = 'Perpindahan Barang';
    protected static ?string $modelLabel = 'Perpindahan Barang';
    protected static ?string $pluralModelLabel = 'Perpindahan Barang';

    public static function form(Schema $schema): Schema
    {
        return PerpindahanBarangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerpindahanBarangsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerpindahanBarangs::route('/'),
            'create' => CreatePerpindahanBarang::route('/create'),
        ];
    }
}
