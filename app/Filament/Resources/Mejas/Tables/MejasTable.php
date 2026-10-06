<?php

namespace App\Filament\Resources\Mejas\Tables;

use App\Models\Meja;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class MejasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_meja')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('zinc'),

                TextColumn::make('nama_meja')
                    ->label('Nama / Area')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang / Cabang')
                    ->sortable(),

                TextColumn::make('area')
                    ->label('Area')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('kapasitas')
                    ->label('Kapasitas')
                    ->numeric()
                    ->suffix(' org'),

                TextColumn::make('durasi_menit')
                    ->label('Durasi')
                    ->suffix(' menit'),

                TextColumn::make('sesi_aktif')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? 'TERISI' : 'Tersedia')
                    ->color(fn (?string $state) => $state === 'terisi' ? 'danger' : 'success')
                    ->state(fn ($record) => $record->sesiAktif?->status)
                    ->sortable(),

                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gudang_id')
                    ->label('Gudang / Cabang')
                    ->relationship('gudang', 'nama_gudang')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('status_aktif')
                    ->label('Aktif')
                    ->default(true),
            ])
            ->recordUrl(null)
            ->recordAction('lihat')
            ->recordActions([
                Action::make('lihat')
                    ->label('Riwayat Pemakaian')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (Meja $record) => "Riwayat Pemakaian - {$record->kode_meja}")
                    ->modalContent(fn (Meja $record): View => view(
                        'filament.resources.meja.riwayat-modal',
                        ['record' => $record->load(['sesi.orderPending', 'sesi.penjualan', 'sesi.karyawan', 'sesi.user'])]
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
