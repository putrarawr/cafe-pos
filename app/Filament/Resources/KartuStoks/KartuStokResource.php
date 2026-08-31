<?php

namespace App\Filament\Resources\KartuStoks;

use App\Filament\Resources\KartuStoks\Pages\ListKartuStoks;
use App\Filament\Resources\KartuStoks\Tables\KartuStoksTable;
use App\Models\KartuStok;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KartuStokResource extends Resource
{
    protected static ?string $model = KartuStok::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Kartu Stok';

    protected static ?string $modelLabel = 'Kartu Stok';

    protected static ?string $pluralModelLabel = 'Kartu Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    // Tidak ada form karena read-only
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return KartuStoksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false; // Tidak bisa create manual, otomatis dari pembelian
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKartuStoks::route('/'),
        ];
    }
}
