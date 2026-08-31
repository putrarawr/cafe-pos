<?php

namespace App\Filament\Resources\Penjualans\Tables;

use App\Models\Penjualan;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class PenjualansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomer_nota')
                    ->label('No. Entry')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('nama_kasir')
                    ->label('Kasir')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('neto')
                    ->label('Total Neto')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('jenis_pembayaran')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'tunai' => 'success',
                        'qris' => 'warning',
                        'transfer' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu Transaksi')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction('view_detail')
            ->filters([
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),

                SelectFilter::make('jenis_pembayaran')
                    ->label('Jenis Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'qris' => 'QRIS',
                        'transfer' => 'Transfer',
                    ]),

                SelectFilter::make('karyawan_id')
                    ->label('Kasir / Karyawan')
                    ->relationship('karyawan', 'nama_karyawan'),
            ])
            ->recordActions([
                Action::make('view_detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (Penjualan $record) => "Detail Penjualan #{$record->nomer_nota}")
                    ->modalContent(fn (Penjualan $record): View => view(
                        'filament.resources.penjualan.detail-modal',
                        ['record' => $record->load('details.barang', 'karyawan', 'user', 'gudang')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->bulkActions([]);
    }
}
