<?php

namespace App\Filament\Resources\Pembelians\Tables;

use App\Models\Pembelian;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class PembeliansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomer_entry')
                    ->label('No. Entry')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('supplier.nama_supplier')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('rincian_barang')
                    ->label('Barang Masuk')
                    ->state(function (Pembelian $record) {
                        $record->loadMissing('details.barang');
                        if ($record->details->isEmpty()) {
                            return '-';
                        }
                        return $record->details->map(function ($d) {
                            $nama = $d->barang?->nama_barang ?? 'Barang';
                            $satuan = $d->satuan ?? 'Pcs';
                            return "{$nama} ({$d->jumlah} {$satuan})";
                        })->implode(', ');
                    })
                    ->wrap()
                    ->limit(45)
                    ->tooltip(function (Pembelian $record) {
                        $record->loadMissing('details.barang');
                        if ($record->details->isEmpty()) {
                            return null;
                        }
                        return $record->details->map(function ($d) {
                            $nama = $d->barang?->nama_barang ?? 'Barang';
                            $satuan = $d->satuan ?? 'Pcs';
                            return "• {$nama}: {$d->jumlah} {$satuan}";
                        })->implode("\n");
                    }),

                TextColumn::make('neto')
                    ->label('Total Neto')
                    ->state(fn (Pembelian $record) => $record->neto > 0 ? $record->neto : $record->total)
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('jenis_pembayaran')
                    ->label('Pembayaran')
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '-'))
                    ->color(fn (?string $state): string => match ($state) {
                        'tunai' => 'success',
                        'transfer' => 'info',
                        'tempo' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label('Diinput oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Waktu Buat')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction('view_detail')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'nama_supplier'),

                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),

                SelectFilter::make('jenis_pembayaran')
                    ->label('Jenis Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer',
                        'tempo' => 'Tempo',
                    ]),
            ])
            ->recordActions([
                Action::make('view_detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (Pembelian $record) => "Detail Pembelian #{$record->nomer_entry}")
                    ->modalContent(fn (Pembelian $record): View => view(
                        'filament.resources.pembelian.detail-modal',
                        ['record' => $record->load('details.barang', 'supplier', 'gudang')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->bulkActions([]);
    }
}
