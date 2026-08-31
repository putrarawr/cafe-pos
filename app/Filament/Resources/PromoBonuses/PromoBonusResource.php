<?php

namespace App\Filament\Resources\PromoBonuses;

use App\Filament\Resources\PromoBonuses\Pages\CreatePromoBonus;
use App\Filament\Resources\PromoBonuses\Pages\EditPromoBonus;
use App\Filament\Resources\PromoBonuses\Pages\ListPromoBonuses;
use App\Filament\Resources\PromoBonuses\Schemas\PromoBonusForm;
use App\Filament\Resources\PromoBonuses\Tables\PromoBonusesTable;
use App\Models\PromoBonus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromoBonusResource extends Resource
{
    protected static ?string $model = PromoBonus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'nama_promo';

    protected static ?string $navigationLabel = 'Promo Bonus';

    protected static ?string $modelLabel = 'Promo Bonus';

    protected static ?string $pluralModelLabel = 'Promo Bonus';

    public static function form(Schema $schema): Schema
    {
        return PromoBonusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoBonusesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoBonuses::route('/'),
            'create' => CreatePromoBonus::route('/create'),
            'edit' => EditPromoBonus::route('/{record}/edit'),
        ];
    }
}
