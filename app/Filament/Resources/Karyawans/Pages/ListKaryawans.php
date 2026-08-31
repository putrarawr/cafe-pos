<?php

namespace App\Filament\Resources\Karyawans\Pages;

use App\Filament\Resources\Karyawans\KaryawanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKaryawans extends ListRecords
{
    protected static string $resource = KaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
