<?php

namespace App\Filament\Resources\PerpindahanBarangs\Pages;

use App\Filament\Resources\PerpindahanBarangs\PerpindahanBarangResource;
use App\Services\StokService;
use Filament\Resources\Pages\CreateRecord;

class CreatePerpindahanBarang extends CreateRecord
{
    protected static string $resource = PerpindahanBarangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(StokService::class)->terapkanPerpindahan($this->record);
    }
}
