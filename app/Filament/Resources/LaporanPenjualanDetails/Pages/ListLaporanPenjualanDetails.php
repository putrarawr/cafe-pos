<?php

namespace App\Filament\Resources\LaporanPenjualanDetails\Pages;

use App\Filament\Resources\LaporanPenjualanDetails\LaporanPenjualanDetailResource;
use App\Filament\Resources\LaporanPenjualanDetails\Widgets\LaporanPenjualanDetailOverview;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListLaporanPenjualanDetails extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = LaporanPenjualanDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LaporanPenjualanDetailOverview::class,
        ];
    }
}
