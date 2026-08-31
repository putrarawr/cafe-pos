<?php

namespace App\Filament\Resources\Gudangs;

use App\Filament\Resources\Gudangs\Pages\CreateGudang;
use App\Filament\Resources\Gudangs\Pages\EditGudang;
use App\Filament\Resources\Gudangs\Pages\ListGudangs;
use App\Filament\Resources\Gudangs\Schemas\GudangForm;
use App\Filament\Resources\Gudangs\Tables\GudangsTable;
use App\Models\Gudang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GudangResource extends Resource
{
    protected static ?string $model = Gudang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'nama_gudang';

    protected static ?string $navigationLabel = 'Gudang';

    protected static ?string $modelLabel = 'Gudang';

    protected static ?string $pluralModelLabel = 'Gudang';

    public static function form(Schema $schema): Schema
    {
        return GudangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GudangsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGudangs::route('/'),
            'create' => CreateGudang::route('/create'),
            'edit' => EditGudang::route('/{record}/edit'),
        ];
    }
}
