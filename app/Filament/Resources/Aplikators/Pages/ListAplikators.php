<?php

namespace App\Filament\Resources\Aplikators\Pages;

use App\Filament\Resources\Aplikators\AplikatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAplikators extends ListRecords
{
    protected static string $resource = AplikatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
