<?php

namespace App\Filament\Resources\Barangs\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class GudangsRelationManager extends RelationManager
{
    protected static string $relationship = 'gudangs';

    protected static ?string $title = 'Stok Gudang';

    public function table(Table $table): Table
    {
        // Stok hanya dapat diubah melalui dokumen transaksi (Pembelian/Perpindahan)
        // Rujuk: docs/future-enhancements/stok-opname.md untuk penyesuaian stok langsung
        return $table
            ->recordTitleAttribute('nama_gudang')
            ->columns([
                TextColumn::make('nama_gudang')
                    ->label('Nama Gudang')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->limit(40),

                TextColumn::make('stok')
                    ->label('Stok')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
