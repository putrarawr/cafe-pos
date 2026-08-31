<?php

namespace App\Filament\Resources\Gudangs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\View\View;

class GudangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_gudang')
                    ->label('Nama Gudang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->limit(40),
                TextColumn::make('barangs_count')
                    ->counts('barangs')
                    ->label('Jumlah Barang')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordUrl(null)
            ->recordAction('lihat_stok')
            ->recordActions([
                Action::make('lihat_stok')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (\App\Models\Gudang $record) => "Daftar Stok Barang - {$record->nama_gudang}")
                    ->modalContent(fn (\App\Models\Gudang $record): View => view(
                        'filament.resources.gudang.detail-stok-modal',
                        ['record' => $record->load('barangs.jenisBarang')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
