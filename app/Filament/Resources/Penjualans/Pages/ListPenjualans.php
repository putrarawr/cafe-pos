<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPenjualans extends ListRecords
{
    protected static string $resource = PenjualanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buka_kasir')
                ->label('Buka Aplikasi Kasir')
                ->icon(Heroicon::OutlinedComputerDesktop)
                ->url(fn () => route('kasir'))
                ->openUrlInNewTab()
                ->color('primary'),
        ];
    }
}
