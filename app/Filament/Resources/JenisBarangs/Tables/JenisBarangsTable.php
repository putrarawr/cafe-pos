<?php

namespace App\Filament\Resources\JenisBarangs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\View\View;

class JenisBarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_jenis')
                    ->label('Nama Jenis')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kode_jenis')
                    ->label('Kode Prefix')
                    ->state(fn ($record) => $record->getEffectiveKodePrefix())
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
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
            ->recordAction('lihat_barang')
            ->recordActions([
                Action::make('lihat_barang')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn(\App\Models\JenisBarang $record) => "Daftar Barang - {$record->nama_jenis}")
                    ->modalContent(fn(\App\Models\JenisBarang $record): View => view(
                        'filament.resources.jenis-barang.detail-barang-modal',
                        ['record' => $record->load('barangs')]
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
