<?php

namespace App\Filament\Resources\HistoriHpps\Pages;

use App\Filament\Resources\HistoriHpps\HistoriHppResource;
use Filament\Resources\Pages\ListRecords;

class ListHistoriHpps extends ListRecords
{
    protected static string $resource = HistoriHppResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
