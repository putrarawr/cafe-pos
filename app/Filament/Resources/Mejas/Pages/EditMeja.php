<?php

namespace App\Filament\Resources\Mejas\Pages;

use App\Filament\Resources\Mejas\MejaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMeja extends EditRecord
{
    protected static string $resource = MejaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
