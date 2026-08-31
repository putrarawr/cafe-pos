<?php

namespace App\Filament\Resources\HistoriHpps\Tables;

use App\Models\HistoriHpp;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistoriHppsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('barang.nomer_seri')
                    ->label('Nomor Seri')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('barang', fn ($q) => $q->where('nama_barang', 'ilike', "%{$search}%")->orWhere('barcode', 'ilike', "%{$search}%")->orWhere('nomer_seri', 'ilike', "%{$search}%")->orWhereHas('jenisBarang', fn ($jq) => $jq->where('nama_jenis', 'ilike', "%{$search}%"))))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('nomer_entry')
                    ->label('No. Entry')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('supplier.nama_supplier')
                    ->label('Supplier')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('qty_beli')
                    ->label('Qty Beli')
                    ->state(function (HistoriHpp $record) {
                        $barang = $record->barang;
                        $satuanBeli = $record->satuan ?? $barang?->satuan ?? 'Pcs';
                        $qty = number_format($record->qty_beli, 0, ',', '.');
                        return "{$qty} {$satuanBeli}";
                    })
                    ->description(function (HistoriHpp $record) {
                        $barang = $record->barang;
                        $satuanBeli = $record->satuan ?? $barang?->satuan ?? 'Pcs';
                        if ($barang && $barang->satuan && $barang->satuan !== $satuanBeli) {
                            $faktor = $barang->getFaktorKonversi($satuanBeli);
                            if ($faktor > 1) {
                                $totalDasar = number_format($record->qty_beli * $faktor, 0, ',', '.');
                                return "{$totalDasar} {$barang->satuan}";
                            }
                        }
                        return null;
                    })
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('harga_beli_masuk')
                    ->label('Harga Beli Satuan')
                    ->state(function (HistoriHpp $record) {
                        $satuanBeli = $record->satuan ?? $record->barang?->satuan ?? 'Pcs';
                        return 'Rp ' . number_format($record->harga_beli_masuk, 0, ',', '.') . ' / ' . $satuanBeli;
                    })
                    ->description(function (HistoriHpp $record) {
                        $barang = $record->barang;
                        $satuanBeli = $record->satuan ?? $barang?->satuan ?? 'Pcs';
                        if ($barang && $barang->satuan && $barang->satuan !== $satuanBeli) {
                            $faktor = $barang->getFaktorKonversi($satuanBeli);
                            if ($faktor > 1) {
                                $hargaDasar = 'Rp ' . number_format(round($record->harga_beli_masuk / $faktor), 0, ',', '.');
                                return "({$hargaDasar} / {$barang->satuan})";
                            }
                        }
                        return null;
                    })
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('stok_sebelum')
                    ->label('Stok Sebelum')
                    ->formatStateUsing(fn ($state, HistoriHpp $record) => number_format($state, 0, ',', '.') . ' ' . ($record->barang?->satuan ?? 'Pcs'))
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('stok_sesudah')
                    ->label('Stok Sesudah')
                    ->formatStateUsing(fn ($state, HistoriHpp $record) => number_format($state, 0, ',', '.') . ' ' . ($record->barang?->satuan ?? 'Pcs'))
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('hpp_sebelum')
                    ->label('HPP Lama')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 2, ',', '.'))
                    ->alignRight()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('hpp_sesudah')
                    ->label('HPP Baru')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 2, ',', '.'))
                    ->alignRight()
                    ->weight('bold')
                    ->color('primary')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('barang_id')
                    ->label('Filter Barang')
                    ->options(function () {
                        return \App\Models\Barang::orderBy('nama_barang')->get()->mapWithKeys(function ($b) {
                            $prefix = $b->nomer_seri ? "[{$b->nomer_seri}] " : '';
                            $barcode = $b->barcode ? " ({$b->barcode})" : '';
                            return [$b->id => "{$prefix}{$b->nama_barang}{$barcode}"];
                        })->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $val) => $q->where('barang_id', $val)
                        );
                    })
                    ->searchable(),
                SelectFilter::make('jenis_barang_id')
                    ->label('Filter Jenis Barang')
                    ->options(function () {
                        return \App\Models\JenisBarang::orderBy('nama_jenis')->get()->mapWithKeys(function ($j) {
                            $prefix = $j->kode_jenis ? "[{$j->kode_jenis}] " : '';
                            return [$j->id => "{$prefix}{$j->nama_jenis}"];
                        })->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $val) => $q->whereHas('barang', fn ($bq) => $bq->where('jenis_barang_id', $val))
                        );
                    })
                    ->searchable(),
                SelectFilter::make('supplier_id')
                    ->label('Filter Supplier')
                    ->relationship('supplier', 'nama_supplier')
                    ->searchable()
                    ->preload(),
                Filter::make('tanggal_range')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '>=', $date)
                            )
                            ->when(
                                $data['sampai_tanggal'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '<=', $date)
                            );
                    }),
            ]);
    }
}
