<?php

namespace App\Filament\Resources\HistoriHpps;

use App\Filament\Resources\HistoriHpps\Pages\ListHistoriHpps;
use App\Filament\Resources\HistoriHpps\Tables\HistoriHppsTable;
use App\Models\HistoriHpp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HistoriHppResource extends Resource
{
    protected static ?string $model = HistoriHpp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Riwayat HPP';

    protected static ?string $modelLabel = 'Riwayat HPP';

    protected static ?string $pluralModelLabel = 'Riwayat HPP';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return HistoriHppsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoriHpps::route('/'),
        ];
    }
}
