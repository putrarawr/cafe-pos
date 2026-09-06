<?php

namespace App\Filament\Resources\Barangs\Tables;

use App\Models\Barang;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

class BarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gambar')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->size(36)
                    ->toggleable(),
                TextColumn::make('jenisBarang.nama_jenis')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tipe_barang')
                    ->label('Tipe')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bahan_baku' => 'Bahan Baku',
                        'setengah_jadi' => 'Setengah Jadi',
                        'barang_jadi' => 'Barang Jadi',
                        'barang_dagang' => 'Barang Dagang',
                        'barang_pembantu' => 'Barang Pembantu',
                        default => ucwords(str_replace('_', ' ', $state ?? '-')),
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'barang_jadi' => 'success',
                        'barang_dagang' => 'info',
                        'setengah_jadi' => 'warning',
                        'bahan_baku' => 'gray',
                        'barang_pembantu' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('nomer_seri')
                    ->label('Nomor Seri')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('status')
                    ->label('Tersedia')
                    ->getStateUsing(fn ($record) => $record->status === 'tersedia')
                    ->beforeStateUpdated(function ($record, $state) {
                        $record->status = $state ? 'tersedia' : 'habis';
                    }),
                IconColumn::make('bisa_dijual')
                    ->label('Dijual')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('butuh_proses')
                    ->label('Proses')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('harga_beli')
                    ->label('Beli Terakhir')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('hpp')
                    ->label('HPP')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('total_stok')
                    ->label('Total Stok')
                    ->state(fn ($record) => $record->gudangs->sum('pivot.stok'))
                    ->formatStateUsing(fn ($record, $state) => number_format((int) $state, 0, ',', '.') . ' ' . e($record->satuan ?? 'Pcs'))
                    ->weight('bold')
                    ->color(function ($record) {
                        $total = (int) $record->gudangs->sum('pivot.stok');
                        return $total <= 0 ? 'danger' : ($total <= 10 ? 'warning' : 'success');
                    })
                    ->tooltip(function ($record): HtmlString {
                        $allGudangs = \App\Models\Gudang::all();
                        if ($allGudangs->isEmpty()) {
                            return new HtmlString('Belum ada gudang terdaftar');
                        }
                        $record->loadMissing('gudangs');
                        $stokMap = $record->gudangs->pluck('pivot.stok', 'id');
                        $satuan = e($record->satuan ?? 'Pcs');

                        $html = '<div style="display:grid;grid-template-columns:1fr auto;gap:16px;min-width:260px;font-size:12px;line-height:1.6;text-align:left">';
                        foreach ($allGudangs as $gudang) {
                            $stok = (int) ($stokMap[$gudang->id] ?? 0);
                            $namaGudang = e($gudang->nama_gudang);
                            $stokText = number_format($stok, 0, ',', '.') . " {$satuan}";
                            $stokColor = $stok > 0 ? '#38bdf8' : '#71717a';
                            $html .= "<div style=\"color:#fafafa\">• {$namaGudang}</div>";
                            $html .= "<div style=\"font-weight:700;text-align:right;color:{$stokColor}\">{$stokText}</div>";
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipe_barang')
                    ->label('Tipe Barang')
                    ->options([
                        'bahan_baku' => 'Bahan Baku',
                        'setengah_jadi' => 'Setengah Jadi',
                        'barang_jadi' => 'Barang Jadi',
                        'barang_dagang' => 'Barang Dagang',
                        'barang_pembantu' => 'Barang Pembantu',
                    ]),
                SelectFilter::make('status')
                    ->label('Ketersediaan')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'habis' => 'Habis',
                    ]),
                SelectFilter::make('jenis_barang_id')
                    ->label('Jenis Barang')
                    ->options(fn () => \App\Models\JenisBarang::orderBy('nama_jenis')->get()->mapWithKeys(fn ($j) => [
                        $j->id => ($j->kode_jenis ? "[{$j->kode_jenis}] " : '') . $j->nama_jenis
                    ]))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('riwayat_hpp')
                    ->label('Riwayat HPP')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('info')
                    ->modalHeading(fn (Barang $record) => "Riwayat HPP - {$record->nama_barang}")
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (Barang $record): View {
                        $histori = $record->historiHpps()->with('supplier')->get();

                        return view('filament.resources.barangs.modal-riwayat-hpp', [
                            'barang' => $record,
                            'histori' => $histori,
                        ]);
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
