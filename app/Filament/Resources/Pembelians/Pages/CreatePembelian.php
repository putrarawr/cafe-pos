<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use App\Services\StokService;
use Filament\Resources\Pages\CreateRecord;

class CreatePembelian extends CreateRecord
{
    protected static string $resource = PembelianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $details = $this->data['details'] ?? [];
        $total = 0;
        foreach ($details as $detail) {
            $total += (int) ($detail['subtotal'] ?? 0);
        }
        $data['total'] = $total;
        $data['neto'] = $total - (int) ($data['diskon'] ?? 0);
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(StokService::class)->terapkanPembelian($this->record);
    }
}
