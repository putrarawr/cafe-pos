<?php

namespace App\Filament\Resources\Barangs\Pages;

use App\Filament\Resources\Barangs\BarangResource;
use App\Filament\Traits\HasPriceCheck;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBarang extends EditRecord
{
    use HasPriceCheck;

    protected static string $resource = BarangResource::class;

    public bool $confirmedLowerPrice = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendNotification = true): void
    {
        $data = $this->form->getState();

        if ($this->hasLowerSellingPrice($data) && ! $this->confirmedLowerPrice) {
            $this->mountAction('confirmLowerPrice', [
                'shouldRedirect' => $shouldRedirect,
                'shouldSendNotification' => $shouldSendNotification,
            ]);
            return;
        }

        parent::save($shouldRedirect, $shouldSendNotification);
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
                $this->save(
                    $arguments['shouldRedirect'] ?? true,
                    $arguments['shouldSendNotification'] ?? true
                );
            });
    }
}
