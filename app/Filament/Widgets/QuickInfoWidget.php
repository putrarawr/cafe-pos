<?php

namespace App\Filament\Widgets;

use App\Models\Barang;
use App\Models\Gudang;
use Filament\Widgets\Widget;

class QuickInfoWidget extends Widget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.quick-info-widget';

    public function getViewData(): array
    {
        return [
            'totalBarang' => Barang::count(),
            'totalGudang' => Gudang::count(),
            'waktu' => now()->setTimezone('Asia/Jakarta')->format('H:i') . ' WIB',
            'tanggal' => now()->setTimezone('Asia/Jakarta')->translatedFormat('l, d F Y'),
        ];
    }
}
