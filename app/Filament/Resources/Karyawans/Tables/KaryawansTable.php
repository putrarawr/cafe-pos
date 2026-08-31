<?php

namespace App\Filament\Resources\Karyawans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KaryawansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_karyawan')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email (Kasir)')
                    ->searchable(),
                TextColumn::make('no_telp')
                    ->label('No. Telepon'),
                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->limit(30),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([])
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
