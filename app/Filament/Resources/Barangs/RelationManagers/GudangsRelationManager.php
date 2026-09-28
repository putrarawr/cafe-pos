<?php

namespace App\Filament\Resources\Barangs\RelationManagers;

use App\Models\Gudang;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class GudangsRelationManager extends RelationManager
{
    protected static string $relationship = 'gudangs';

    protected static ?string $title = 'Stok Gudang';

    public function ensureAllGudangsAttached(): void
    {
        $barang = $this->getOwnerRecord();
        if (! $barang || ! $barang->exists) {
            return;
        }

        $allGudangIds = Gudang::pluck('id')->all();
        $attachedGudangIds = DB::table('barang_gudang')
            ->where('barang_id', $barang->id)
            ->pluck('gudang_id')
            ->all();

        $missing = array_diff($allGudangIds, $attachedGudangIds);
        if (! empty($missing)) {
            $now = now();
            $defaultStokMin = (int) ($barang->stok_minimum ?? 20);
            $rows = [];
            foreach ($missing as $gudangId) {
                $rows[] = [
                    'barang_id' => $barang->id,
                    'gudang_id' => $gudangId,
                    'stok' => 0,
                    'stok_minimum' => $defaultStokMin,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('barang_gudang')->insertOrIgnore($rows);
            $barang->unsetRelation('gudangs');
        }
    }

    public function table(Table $table): Table
    {
        $this->ensureAllGudangsAttached();

        return $table
            ->recordTitleAttribute('nama_gudang')
            ->columns([
                TextColumn::make('nama_gudang')
                    ->label('Nama Gudang')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->limit(50),

                TextColumn::make('stok')
                    ->label('Stok Saat Ini')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => number_format((int) ($record->pivot->stok ?? $state ?? 0), 0, ',', '.')),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->bulkActions([])
            ->paginated(false);
    }
}
