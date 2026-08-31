<?php

namespace App\Filament\Resources\PerpindahanBarangs\Pages;

use App\Filament\Resources\PerpindahanBarangs\PerpindahanBarangResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerpindahanBarangs extends ListRecords
{
    protected static string $resource = PerpindahanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
