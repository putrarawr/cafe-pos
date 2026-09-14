<?php

namespace App\Filament\Resources\Aplikators\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AplikatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_aplikator')
                    ->label('Nama Aplikator')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('kode_aplikator')
                    ->label('Kode')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('persentase_komisi')
                    ->label('Komisi Platform')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.') . '%')
                    ->badge()
                    ->color(fn ($state) => (float) $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('barangs_count')
                    ->counts('barangs')
                    ->label('Jumlah Menu Terdaftar')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                ToggleColumn::make('status_aktif')
                    ->label('Aktif')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
