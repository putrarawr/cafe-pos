<?php

namespace App\Filament\Resources\KartuStoks\Tables;

use App\Models\KartuStok;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class KartuStoksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                $filters = $livewire->tableFilters ?? [];
                $search = $livewire->tableSearch ?? null;

                $hasActiveFilter = false;

                if (! empty($search)) {
                    $hasActiveFilter = true;
                } else {
                    foreach ($filters as $filterData) {
                        if (is_array($filterData)) {
                            foreach ($filterData as $value) {
                                if ($value !== null && $value !== '' && $value !== []) {
                                    $hasActiveFilter = true;
                                    break 2;
                                }
                            }
                        } elseif ($filterData !== null && $filterData !== '') {
                            $hasActiveFilter = true;
                            break;
                        }
                    }
                }

                if (! $hasActiveFilter) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->emptyStateHeading('Pilih Filter Kartu Stok')
            ->emptyStateDescription('Silakan pilih filter barang, gudang, atau jenis transaksi terlebih dahulu untuk melihat data kartu stok.')
            ->emptyStateIcon(Heroicon::OutlinedFunnel)
            ->columns([
                TextColumn::make('nomer_seri')
                    ->label('Nomor Seri')
                    ->state(fn (KartuStok $record) => $record->nomer_seri ?? $record->barang?->nomer_seri ?? '-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('kartu_stok.nomer_seri', 'ilike', "%{$search}%")->orWhereHas('barang', fn ($q) => $q->where('nomer_seri', 'ilike', "%{$search}%")))
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('barang', fn ($q) => $q->where('nama_barang', 'ilike', "%{$search}%")->orWhere('barcode', 'ilike', "%{$search}%")->orWhere('nomer_seri', 'ilike', "%{$search}%")->orWhereHas('jenisBarang', fn ($jq) => $jq->where('nama_jenis', 'ilike', "%{$search}%"))))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('nomer_entry')
                    ->label('No. Entry')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Tanggal & Jam')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn (KartuStok $record): string => $record->keterangan ?? ''),

                TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'masuk', 'pindah_masuk' => 'success',
                        'keluar', 'pindah_keluar' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->state(function (KartuStok $record) {
                        $prefix = $record->jumlah > 0 ? '+' : '';
                        $satuan = $record->barang?->satuan ?? 'Pcs';
                        return $prefix . number_format($record->jumlah, 0, ',', '.') . ' ' . $satuan;
                    })
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->formatStateUsing(function ($state, KartuStok $record) {
                        $satuan = $record->barang?->satuan ?? 'Pcs';
                        return 'Rp ' . number_format($state, 0, ',', '.') . ' / ' . $satuan;
                    }),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state, KartuStok $record) => number_format($state, 0, ',', '.') . ' ' . ($record->barang?->satuan ?? 'Pcs'))
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordAction('view_detail')
            ->recordActions([
                Action::make('view_detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (KartuStok $record) => "Detail Mutasi Stok #" . ($record->nomer_entry ?? $record->id))
                    ->modalWidth('2xl')
                    ->modalContent(fn (KartuStok $record): View => view(
                        'filament.resources.kartu-stok.detail-modal',
                        ['record' => $record->load('barang.jenisBarang', 'gudang')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
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

                SelectFilter::make('gudang_id')
                    ->label('Filter Gudang')
                    ->relationship('gudang', 'nama_gudang')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options([
                        'masuk' => 'Masuk',
                        'keluar' => 'Keluar',
                        'pindah_masuk' => 'Pindah Masuk',
                        'pindah_keluar' => 'Pindah Keluar',
                    ]),
            ]);
    }
}
