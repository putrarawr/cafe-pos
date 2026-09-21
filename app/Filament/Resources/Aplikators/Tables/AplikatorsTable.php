<?php

namespace App\Filament\Resources\Aplikators\Tables;

use App\Models\Aplikator;
use App\Services\HargaAplikatorService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AplikatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gambar')
                    ->label('Logo')
                    ->disk('public')
                    ->height(28)
                    ->placeholder('-'),

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
                Action::make('hitungUlangHarga')
                    ->label('Hitung Ulang Harga')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Hitung ulang harga delivery?')
                    ->modalDescription('Semua harga jual delivery untuk aplikator ini akan dihitung ulang dari harga normal + persentase komisi. Perubahan manual pada harga delivery akan ditimpa.')
                    ->modalSubmitActionLabel('Ya, hitung ulang')
                    ->visible(fn (Aplikator $record): bool => (float) $record->persentase_komisi > 0)
                    ->action(function (Aplikator $record): void {
                        $jumlah = app(HargaAplikatorService::class)->sinkronkan($record);

                        Notification::make()
                            ->title("Harga delivery {$record->nama_aplikator} diperbarui")
                            ->body("{$jumlah} barang dihitung ulang dari harga normal + {$record->persentase_komisi}% komisi.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
