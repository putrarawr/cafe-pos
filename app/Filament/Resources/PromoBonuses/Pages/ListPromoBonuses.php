<?php

namespace App\Filament\Resources\PromoBonuses\Pages;

use App\Filament\Resources\PromoBonuses\PromoBonusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoBonuses extends ListRecords
{
    protected static string $resource = PromoBonusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
