<?php

namespace App\Filament\Resources\PerpindahanBarangs\Pages;

use App\Filament\Resources\PerpindahanBarangs\PerpindahanBarangResource;
use App\Filament\Resources\PerpindahanBarangs\Schemas\PerpindahanBarangForm;
use App\Services\StokService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePerpindahanBarang extends CreateRecord
{
    protected static string $resource = PerpindahanBarangResource::class;

    public bool $confirmedOverstock = false;

    public function create(bool $another = false): void
    {
        $overstocks = PerpindahanBarangForm::getOverstockItemsFromState($this->form->getState());

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
            ->modalHeading('Peringatan: Pemindahan Melebihi Batas Stok Maksimum')
            ->modalDescription(function (): string {
                $overstocks = PerpindahanBarangForm::getOverstockItemsFromState($this->form->getState());
                $lines = [];
                foreach ($overstocks as $item) {
                    $lines[] = "• {$item['nama_barang']}: {$item['pesan']}";
                }
                $msg = "Beberapa barang dalam mutasi ini melebihi kapasitas stok maksimum gudang tujuan:\n\n";
                $msg .= implode("\n", $lines);
                $msg .= "\n\nMutasi tetap dapat diproses jika diperlukan. Apakah Anda yakin ingin tetap melanjutkan perpindahan barang ini?";

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
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(StokService::class)->terapkanPerpindahan($this->record);
    }
}
