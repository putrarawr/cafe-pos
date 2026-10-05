<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use App\Filament\Resources\Pembelians\Schemas\PembelianForm;
use App\Services\StokService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePembelian extends CreateRecord
{
    protected static string $resource = PembelianResource::class;

    public bool $confirmedOverstock = false;

    public function create(bool $another = false): void
    {
        $overstocks = PembelianForm::getOverstockItemsFromState($this->form->getState());

        if (! empty($overstocks) && ! $this->confirmedOverstock) {
            $this->mountAction('confirmOverstock', ['another' => $another]);
            return;
        }

        parent::create($another);
    }

    public function confirmOverstockAction(): Action
    {
        return Action::make('confirmOverstock')
            ->requiresConfirmation()
            ->modalHeading('Peringatan: Pembelian Melebihi Batas Stok Maksimum')
            ->modalDescription(function (): string {
                $overstocks = PembelianForm::getOverstockItemsFromState($this->form->getState());
                $lines = [];
                foreach ($overstocks as $item) {
                    $lines[] = "• {$item['nama_barang']}: {$item['pesan']}";
                }
                $msg = "Beberapa barang dalam pembelian ini melebihi batas stok maksimum gudang tujuan:\n\n";
                $msg .= implode("\n", $lines);
                $msg .= "\n\nStok berlebih tetap dapat disimpan ke sistem. Apakah Anda yakin ingin tetap melanjutkan transaksi ini?";

                return $msg;
            })
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalSubmitActionLabel('Tetap Lanjutkan')
            ->modalCancelActionLabel('Periksa Kembali')
            ->action(function (array $arguments): void {
                $this->confirmedOverstock = true;
                $this->create($arguments['another'] ?? false);
            });
    }

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
