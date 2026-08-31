<?php

namespace App\Filament\Resources\Gudangs\Pages;

use App\Filament\Resources\Gudangs\GudangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGudangs extends ListRecords
{
    protected static string $resource = GudangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
