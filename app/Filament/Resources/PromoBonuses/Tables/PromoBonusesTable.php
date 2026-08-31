<?php

namespace App\Filament\Resources\PromoBonuses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromoBonusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_promo')
                    ->label('Nama Promo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barangUtama.nama_barang')
                    ->label('Syarat Pembelian')
                    ->formatStateUsing(fn ($record) => "Beli {$record->min_qty_utama} " . ($record->satuan_utama ?? $record->barangUtama?->satuan ?? 'Pcs') . " " . ($record->barangUtama?->nama_barang ?? '-'))
                    ->sortable(),
                TextColumn::make('barangBonus.nama_barang')
                    ->label('Gratis / Bonus')
                    ->formatStateUsing(fn ($record) => "Gratis {$record->qty_bonus} " . ($record->satuan_bonus ?? $record->barangBonus?->satuan ?? 'Pcs') . " " . ($record->barangBonus?->nama_barang ?? '-'))
                    ->sortable(),
                IconColumn::make('is_kelipatan')
                    ->label('Kelipatan')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_aktif')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('tanggal_mulai')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('tanggal_selesai')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
