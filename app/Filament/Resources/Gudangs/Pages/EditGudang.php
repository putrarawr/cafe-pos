<?php

namespace App\Filament\Resources\Gudangs\Pages;

use App\Filament\Resources\Gudangs\GudangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGudang extends EditRecord
{
    protected static string $resource = GudangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
