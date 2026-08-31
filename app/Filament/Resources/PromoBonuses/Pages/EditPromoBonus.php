<?php

namespace App\Filament\Resources\PromoBonuses\Pages;

use App\Filament\Resources\PromoBonuses\PromoBonusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoBonus extends EditRecord
{
    protected static string $resource = PromoBonusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
