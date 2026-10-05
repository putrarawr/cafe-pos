<?php

namespace App\Filament\Resources\Barangs\Pages;

use App\Filament\Resources\Barangs\BarangResource;
use App\Filament\Resources\Barangs\Schemas\BarangForm;
use App\Filament\Traits\HasPriceCheck;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $gudangs = \App\Models\Gudang::all();
        $pivots = DB::table('barang_gudang')
            ->where('barang_id', $record->id)
            ->pluck('stok_minimum', 'gudang_id')
            ->all();

        $statusGudang = [];
        $stokGudangDisplay = [];
        $satuanGudang = [];
        $sumGudangBase = 0;

        foreach ($gudangs as $g) {
            $baseGudang = isset($pivots[$g->id]) ? (int) $pivots[$g->id] : 0;
            $isGudangActive = ($baseGudang > 0);
            $deconstructed = BarangForm::deconstructBaseQtyToBestUnit($isGudangActive ? $baseGudang : 20, $record);
            $statusGudang[$g->id] = $isGudangActive;
            $stokGudangDisplay[$g->id] = $deconstructed['qty'];
            $satuanGudang[$g->id] = $deconstructed['satuan'];

            if ($isGudangActive) {
                $sumGudangBase += $baseGudang;
            }
        }

        $baseGlobal = (int) ($record->stok_minimum ?? 0);
        $isGlobalActive = ($baseGlobal > 0);

        $data['pantau_stok_global'] = $isGlobalActive;
        $data['stok_minimum_display'] = $isGlobalActive ? $sumGudangBase : 0;
        $data['satuan_stok_minimum'] = $record->satuan ?? 'Pcs';
        $data['status_pantau_gudang'] = $statusGudang;
        $data['stok_minimum_gudang_display'] = $stokGudangDisplay;
        $data['satuan_stok_minimum_gudang'] = $satuanGudang;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $raw = $this->form->getRawState();
        $record = $this->getRecord();

        if (($data['tipe_barang'] ?? $record->tipe_barang) === 'barang_jadi') {
            $data['stok_minimum'] = 0;
        } else {
            $isGlobalActive = array_key_exists('pantau_stok_global', $raw)
                ? (bool) $raw['pantau_stok_global']
                : (bool) ($data['pantau_stok_global'] ?? true);

            if (! $isGlobalActive) {
                $data['stok_minimum'] = 0;
            } else {
                $gudangs = \App\Models\Gudang::all();
                $statusGudangs = $raw['status_pantau_gudang'] ?? ($data['status_pantau_gudang'] ?? []);
                $gudangDisplays = $raw['stok_minimum_gudang_display'] ?? ($data['stok_minimum_gudang_display'] ?? []);
                $satuanGudangs = $raw['satuan_stok_minimum_gudang'] ?? ($data['satuan_stok_minimum_gudang'] ?? []);

                $total = 0;
                foreach ($gudangs as $g) {
                    $isActive = array_key_exists($g->id, $statusGudangs)
                        ? (bool) $statusGudangs[$g->id]
                        : true;
                    if ($isActive) {
                        $qtyVal = (int) ($gudangDisplays[$g->id] ?? 20);
                        $unit = $satuanGudangs[$g->id] ?? ($record->satuan ?? 'Pcs');
                        $faktor = $record->getFaktorKonversi($unit);
                        $total += ($qtyVal * $faktor);
                    }
                }
                $data['stok_minimum'] = $total;
            }
        }

        unset(
            $data['pantau_stok_global'],
            $data['stok_minimum_display'],
            $data['satuan_stok_minimum'],
            $data['sinkron_ke_gudang'],
            $data['status_pantau_gudang'],
            $data['stok_minimum_gudang_display'],
            $data['satuan_stok_minimum_gudang']
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $raw = $this->form->getRawState();
        $record = $this->getRecord();
        $recordId = $record->id;
        $isBarangJadi = (($raw['tipe_barang'] ?? $record->tipe_barang) === 'barang_jadi');

        if ($isBarangJadi) {
            DB::table('barang_gudang')
                ->where('barang_id', $recordId)
                ->update([
                    'stok_minimum' => 0,
                    'updated_at' => now(),
                ]);
            return;
        }

        $gudangs = \App\Models\Gudang::all();
        $statusGudangs = $raw['status_pantau_gudang'] ?? [];
        $gudangDisplays = $raw['stok_minimum_gudang_display'] ?? [];
        $satuanGudangs = $raw['satuan_stok_minimum_gudang'] ?? [];

        foreach ($gudangs as $g) {
            $gudangId = $g->id;
            $isActive = array_key_exists($gudangId, $statusGudangs)
                ? (bool) $statusGudangs[$gudangId]
                : true;

            if (! $isActive) {
                $baseVal = 0;
            } else {
                $qtyVal = $gudangDisplays[$gudangId] ?? 20;
                $unit = $satuanGudangs[$gudangId] ?? ($record->satuan ?? 'Pcs');
                $faktor = $record->getFaktorKonversi($unit);
                $baseVal = (int) $qtyVal * $faktor;
            }

            $existing = DB::table('barang_gudang')
                ->where('barang_id', $recordId)
                ->where('gudang_id', $gudangId)
                ->first();

            if ($existing) {
                DB::table('barang_gudang')
                    ->where('id', $existing->id)
                    ->update([
                        'stok_minimum' => $baseVal,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('barang_gudang')->insert([
                    'barang_id' => $recordId,
                    'gudang_id' => $gudangId,
                    'stok' => 0,
                    'stok_minimum' => $baseVal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
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
