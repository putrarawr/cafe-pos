<?php

namespace App\Filament\Resources\Barangs\Pages;

use App\Filament\Resources\Barangs\BarangResource;
use App\Filament\Resources\Barangs\Schemas\BarangForm;
use App\Filament\Traits\HasPriceCheck;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateBarang extends CreateRecord
{
    use HasPriceCheck;

    protected static string $resource = BarangResource::class;

    public bool $confirmedLowerPrice = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $raw = $this->form->getRawState();

        if (($data['tipe_barang'] ?? '') === 'barang_jadi') {
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
                        $qty = (int) ($gudangDisplays[$g->id] ?? 20);
                        $unit = $satuanGudangs[$g->id] ?? ($data['satuan'] ?? 'Pcs');
                        $faktor = BarangForm::getFaktorForUnit($unit, fn ($k) => $raw[$k] ?? ($data[$k] ?? null), null);
                        $total += ($qty * $faktor);
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

    protected function afterCreate(): void
    {
        $raw = $this->form->getRawState();
        $record = $this->getRecord();
        $recordId = $record->id;
        $isBarangJadi = (($raw['tipe_barang'] ?? $record->tipe_barang) === 'barang_jadi');

        if ($isBarangJadi) {
            \Illuminate\Support\Facades\DB::table('barang_gudang')
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

            $existing = \Illuminate\Support\Facades\DB::table('barang_gudang')
                ->where('barang_id', $recordId)
                ->where('gudang_id', $gudangId)
                ->first();

            if ($existing) {
                \Illuminate\Support\Facades\DB::table('barang_gudang')
                    ->where('id', $existing->id)
                    ->update([
                        'stok_minimum' => $baseVal,
                        'updated_at' => now(),
                    ]);
            } else {
                \Illuminate\Support\Facades\DB::table('barang_gudang')->insert([
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
