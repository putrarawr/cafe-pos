<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),

                TextColumn::make('causer')
                    ->label('Pelaku')
                    ->formatStateUsing(function (mixed $state, Activity $record): string {
                        if (! $record->causer) {
                            return 'Sistem / Guest';
                        }

                        return $record->causer->name ?? $record->causer->nama_karyawan ?? class_basename($record->causer_type) . " #{$record->causer_id}";
                    })
                    ->description(function (Activity $record): ?string {
                        return $record->causer_type ? class_basename($record->causer_type) : null;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph('causer', ['*'], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('nama_karyawan', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('causer_id', $direction);
                    }),

                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match (strtolower($state ?? '')) {
                        'created' => 'DIBUAT',
                        'updated' => 'DIUBAH',
                        'deleted' => 'DIHAPUS',
                        'login', 'logged_in' => 'LOGIN',
                        default => strtoupper($state ?? 'CUSTOM'),
                    })
                    ->color(fn (?string $state): string => match (strtolower($state ?? '')) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'login', 'logged_in' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return '-';
                        }
                        $base = class_basename($state);

                        return match ($base) {
                            'Penjualan' => 'Penjualan',
                            'Pembelian' => 'Pembelian',
                            'Barang' => 'Barang',
                            'Gudang' => 'Gudang',
                            'Karyawan' => 'Karyawan',
                            'PerpindahanBarang' => 'Perpindahan Barang',
                            'Supplier' => 'Supplier',
                            'JenisBarang' => 'Jenis Barang',
                            'User' => 'User Admin',
                            default => $base,
                        };
                    })
                    ->description(function (Activity $record): ?string {
                        if (! $record->subject) {
                            return $record->subject_id ? "ID: #{$record->subject_id}" : null;
                        }
                        $s = $record->subject;
                        $ref = $s->nomer_nota ?? $s->nomer_entry ?? $s->nama_barang ?? $s->nama_gudang ?? $s->nama_karyawan ?? $s->nama_supplier ?? $s->nama_jenis ?? $s->name ?? null;

                        return $ref ? "Ref: {$ref}" : "ID: #{$record->subject_id}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('properties')
                    ->label('Perubahan')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! $state) {
                            return '-';
                        }
                        $props = is_array($state) ? $state : $state->toArray();
                        $attributes = $props['attributes'] ?? [];

                        if (empty($attributes)) {
                            return '-';
                        }

                        $keys = array_keys($attributes);

                        return count($keys) > 3
                            ? implode(', ', array_slice($keys, 0, 3)) . ' (+' . (count($keys) - 3) . ' lainnya)'
                            : implode(', ', $keys);
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction('view_detail')
            ->filters([
                SelectFilter::make('event')
                    ->label('Jenis Aksi')
                    ->options([
                        'created' => 'Dibuat (Created)',
                        'updated' => 'Diubah (Updated)',
                        'deleted' => 'Dihapus (Deleted)',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Tipe Objek')
                    ->options([
                        'App\Models\Penjualan' => 'Penjualan',
                        'App\Models\Pembelian' => 'Pembelian',
                        'App\Models\Barang' => 'Barang',
                        'App\Models\Gudang' => 'Gudang',
                        'App\Models\Karyawan' => 'Karyawan',
                        'App\Models\PerpindahanBarang' => 'Perpindahan Barang',
                        'App\Models\Supplier' => 'Supplier',
                        'App\Models\JenisBarang' => 'Jenis Barang',
                    ]),
            ])
            ->recordActions([
                Action::make('view_detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Activity $record) => "Detail Riwayat Aktivitas #{$record->id}")
                    ->modalContent(fn (Activity $record): View => view(
                        'filament.resources.activity.detail-modal',
                        ['record' => $record->load('causer', 'subject')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->bulkActions([]);
    }
}
