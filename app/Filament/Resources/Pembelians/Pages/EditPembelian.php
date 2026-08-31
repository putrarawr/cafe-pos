<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Exceptions\StokTidakCukupException;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Services\StokService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPembelian extends EditRecord
{
    protected static string $resource = PembelianResource::class;

    private array $snapshotLama = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $details = $this->data['details'] ?? [];
        $total = 0;
        foreach ($details as $detail) {
            $total += (int) ($detail['subtotal'] ?? 0);
        }
        $data['total'] = $total;
        $data['neto'] = $total - (int) ($data['diskon'] ?? 0);

        return $data;
    }

    protected function beforeSave(): void
    {
        $stok = app(StokService::class);
        $record = $this->record->fresh();
        $this->snapshotLama = $stok->snapshotPembelian($record);

        // delta = -lama +baru per (barang, gudang); cek tidak bikin minus
        $deltas = [];
        foreach ($this->snapshotLama['details'] as $d) {
            $kunci = "{$d['barang_id']}:{$this->snapshotLama['gudang_id']}";
            $deltas[$kunci] = ($deltas[$kunci] ?? 0) - $d['jumlah'];
        }
        $gudangBaru = (int) $this->data['gudang_id'];
        foreach ($this->data['details'] ?? [] as $d) {
            if (empty($d['barang_id'])) {
                continue;
            }
            $kunci = "{$d['barang_id']}:{$gudangBaru}";
            $deltas[$kunci] = ($deltas[$kunci] ?? 0) + (int) $d['jumlah'];
        }

        try {
            $stok->validasiDelta($deltas);
        } catch (StokTidakCukupException $e) {
            Notification::make()->danger()
                ->title('Perubahan ditolak')
                ->body($e->getMessage())
                ->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        $stok = app(StokService::class);
        DB::transaction(function () use ($stok) {
            // balikkan efek lama tanpa validasi per-langkah (sudah divalidasi via delta),
            // lalu terapkan efek baru
            foreach ($this->snapshotLama['details'] as $d) {
                $stok->kurangiStok($d['barang_id'], $this->snapshotLama['gudang_id'], $d['jumlah'], [
                    'nomer_entry' => $this->snapshotLama['nomer_entry'],
                    'jenis' => \App\Models\KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan (edit) pembelian ' . $this->snapshotLama['nomer_entry'],
                ], validasi: false);
            }
            $stok->terapkanPembelian($this->record->fresh());
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function ($record, DeleteAction $action) {
                    $stok = app(StokService::class);
                    try {
                        return DB::transaction(function () use ($stok, $record) {
                            $stok->balikkanPembelian($stok->snapshotPembelian($record));
                            return $record->delete();
                        });
                    } catch (StokTidakCukupException $e) {
                        Notification::make()->danger()
                            ->title('Tidak bisa menghapus')
                            ->body($e->getMessage())
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
