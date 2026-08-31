<?php

namespace App\Filament\Resources\LaporanPenjualanDetails\Tables;

use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Karyawan;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanPenjualanDetailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_bonus', false)->with(['penjualan.user', 'penjualan.karyawan', 'barang', 'bonusBarang']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('penjualan.nomer_nota')
                    ->label('No. Entry')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Waktu Transaksi')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),

                TextColumn::make('penjualan.nama_kasir')
                    ->label('Kasir')
                    ->state(fn (DetailJual $record) => $record->penjualan?->nama_kasir ?? '-')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('penjualan', function ($q) use ($search) {
                            $q->whereHas('karyawan', fn ($qk) => $qk->where('nama_karyawan', 'like', "%{$search}%"))
                                ->orWhereHas('user', fn ($qu) => $qu->where('name', 'like', "%{$search}%"));
                        });
                    }),

                TextColumn::make('barang.nama_barang')
                    ->label('Barang Utama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (DetailJual $record) => $record->barang?->nomer_seri ?: null),

                TextColumn::make('jumlah')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state, DetailJual $record) => number_format($state, 0, ',', '.') . ' ' . ($record->satuan ?? $record->barang?->satuan ?? 'Pcs'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('harga')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('hpp')
                    ->label('HPP Satuan')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('bonusBarang.nama_barang')
                    ->label('Bonus Barang')
                    ->placeholder('-')
                    ->description(fn (DetailJual $record) => $record->bonusBarang?->nomer_seri ?: null)
                    ->searchable(),

                TextColumn::make('bonus_qty')
                    ->label('Qty Bonus')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state, DetailJual $record) => $state ? number_format($state, 0, ',', '.') . ' ' . ($record->bonus_satuan ?? $record->bonusBarang?->satuan ?? 'Pcs') : '-')
                    ->alignCenter(),

                TextColumn::make('bonus_hpp')
                    ->label('HPP Bonus')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 2, ',', '.') : '-')
                    ->alignRight(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('laba_bersih')
                    ->label('Laba Bersih')
                    ->state(fn (DetailJual $record): float => $record->laba_bersih)
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->alignRight()
                    ->weight('bold')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('detail_jual.created_at', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('detail_jual.created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators[] = 'Dari: ' . \Carbon\Carbon::parse($data['dari_tanggal'])->format('d M Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators[] = 'Sampai: ' . \Carbon\Carbon::parse($data['sampai_tanggal'])->format('d M Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('kasir')
                    ->label('Filter Kasir')
                    ->options(function () {
                        $options = [];
                        $karyawans = Karyawan::orderBy('nama_karyawan')->get();
                        foreach ($karyawans as $k) {
                            $options['karyawan_' . $k->getKey()] = $k->nama_karyawan . ' [Kasir]';
                        }
                        $users = User::orderBy('name')->get();
                        foreach ($users as $u) {
                            $options['user_' . $u->getKey()] = $u->name . ' [Admin]';
                        }
                        return $options;
                    })
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }
                        $val = $data['value'];
                        if (str_starts_with($val, 'karyawan_')) {
                            $karyawanId = (int) substr($val, 9);
                            $query->whereHas('penjualan', fn ($q) => $q->where('karyawan_id', $karyawanId));
                        } elseif (str_starts_with($val, 'user_')) {
                            $userId = (int) substr($val, 5);
                            $query->whereHas('penjualan', fn ($q) => $q->where('user_id', $userId));
                        }
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('barang_id')
                    ->label('Filter Barang')
                    ->options(function () {
                        return Barang::orderBy('nama_barang')->get()->mapWithKeys(function ($b) {
                            $prefix = $b->nomer_seri ? "[{$b->nomer_seri}] " : '';
                            return [$b->id => "{$prefix}{$b->nama_barang}"];
                        });
                    })
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('bonus')
                    ->label('Status Bonus')
                    ->placeholder('Semua Transaksi')
                    ->trueLabel('Hanya Berbonus')
                    ->falseLabel('Tanpa Bonus')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('bonus_barang_id')->where('bonus_qty', '>', 0),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('bonus_barang_id')->orWhere('bonus_qty', '<=', 0)),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
