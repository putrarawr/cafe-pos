<?php

namespace App\Filament\Resources\Aplikators\Pages;

use App\Filament\Resources\Aplikators\AplikatorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAplikator extends EditRecord
{
    protected static string $resource = AplikatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
