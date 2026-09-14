<?php

namespace App\Filament\Resources\Aplikators;

use App\Filament\Resources\Aplikators\Pages\CreateAplikator;
use App\Filament\Resources\Aplikators\Pages\EditAplikator;
use App\Filament\Resources\Aplikators\Pages\ListAplikators;
use App\Filament\Resources\Aplikators\Schemas\AplikatorForm;
use App\Filament\Resources\Aplikators\Tables\AplikatorsTable;
use App\Models\Aplikator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AplikatorResource extends Resource
{
    protected static ?string $model = Aplikator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'nama_aplikator';

    protected static ?string $navigationLabel = 'Aplikator Delivery';

    protected static ?string $modelLabel = 'Aplikator';

    protected static ?string $pluralModelLabel = 'Aplikator Delivery';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return AplikatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AplikatorsTable::configure($table);
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
            'index' => ListAplikators::route('/'),
            'create' => CreateAplikator::route('/create'),
            'edit' => EditAplikator::route('/{record}/edit'),
        ];
    }
}
