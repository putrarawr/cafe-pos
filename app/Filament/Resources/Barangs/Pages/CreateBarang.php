<?php

namespace App\Filament\Resources\Barangs\Pages;

use App\Filament\Resources\Barangs\BarangResource;
use App\Filament\Traits\HasPriceCheck;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateBarang extends CreateRecord
{
    use HasPriceCheck;

    protected static string $resource = BarangResource::class;

    public bool $confirmedLowerPrice = false;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();

        if ($this->hasLowerSellingPrice($data) && ! $this->confirmedLowerPrice) {
            $this->mountAction('confirmLowerPrice', ['another' => $another]);
            return;
        }

        parent::create($another);
    }

    public function confirmLowerPriceAction(): Action
    {
        return Action::make('confirmLowerPrice')
            ->requiresConfirmation()
            ->modalHeading('Peringatan: Harga Jual Lebih Rendah Dari Harga Beli')
            ->modalDescription(fn (): string => $this->getLowerPriceWarningMessage($this->form->getState()))
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalSubmitActionLabel('Lanjut')
            ->modalCancelActionLabel('Batal')
            ->action(function (array $arguments): void {
                $this->confirmedLowerPrice = true;
                $this->create($arguments['another'] ?? false);
            });
    }
}
