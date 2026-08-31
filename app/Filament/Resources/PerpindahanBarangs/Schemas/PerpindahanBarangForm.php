<?php

namespace App\Filament\Resources\PerpindahanBarangs\Schemas;

use App\Models\Barang;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class PerpindahanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nomer_entry')
                    ->label('Nomer Entry')
                    ->default('Otomatis')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpan(2),
                Select::make('gudang_asal_id')
                    ->label('Gudang Asal')
                    ->relationship('gudangAsal', 'nama_gudang', function (Builder $query, Get $get) {
                        if ($tujuanId = $get('gudang_tujuan_id')) {
                            $query->where('id', '!=', $tujuanId);
                        }
                    })
                    ->required()->searchable()->preload()->reactive(),
                Select::make('gudang_tujuan_id')
                    ->label('Gudang Tujuan')
                    ->relationship('gudangTujuan', 'nama_gudang', function (Builder $query, Get $get) {
                        if ($asalId = $get('gudang_asal_id')) {
                            $query->where('id', '!=', $asalId);
                        }
                    })
                    ->required()->searchable()->preload()->reactive()
                    ->different('gudang_asal_id')
                    ->validationMessages(['different' => 'Gudang tujuan harus beda dari gudang asal.']),
                DatePicker::make('tanggal')
                    ->label('Tanggal')->default(now())->required(),
                Textarea::make('keterangan')
                    ->label('Keterangan')->rows(2)->columnSpanFull(),

                Repeater::make('details')
                    ->label('Daftar Barang')
                    ->relationship()
                    ->schema([
                        Select::make('barang_id')
                            ->label('Barang')
                            ->options(function (Get $get) {
                                $gudangId = $get('../../gudang_asal_id');
                                if (! $gudangId) {
                                    return [];
                                }
                                return DB::table('barang_gudang')
                                    ->join('barang', 'barang.id', '=', 'barang_gudang.barang_id')
                                    ->where('barang_gudang.gudang_id', $gudangId)
                                    ->where('barang_gudang.stok', '>', 0)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $prefix = $item->nomer_seri ? "[{$item->nomer_seri}] " : '';
                                        return [$item->barang_id => "{$prefix}{$item->nama_barang} (stok: {$item->stok} " . ($item->satuan ?? 'Pcs') . ')'];
                                    });
                            })
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $gudangId = $get('../../gudang_asal_id');
                                if (! $gudangId) {
                                    return [];
                                }
                                return DB::table('barang_gudang')
                                    ->join('barang', 'barang.id', '=', 'barang_gudang.barang_id')
                                    ->where('barang_gudang.gudang_id', $gudangId)
                                    ->where('barang_gudang.stok', '>', 0)
                                    ->where(function ($q) use ($search) {
                                        $q->where('barang.nama_barang', 'ilike', "%{$search}%")
                                            ->orWhere('barang.nomer_seri', 'ilike', "%{$search}%")
                                            ->orWhere('barang.barcode', 'ilike', "%{$search}%");
                                    })
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $prefix = $item->nomer_seri ? "[{$item->nomer_seri}] " : '';
                                        return [$item->barang_id => "{$prefix}{$item->nama_barang} (stok: {$item->stok} " . ($item->satuan ?? 'Pcs') . ')'];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->reactive()
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, $state) {
                                if ($state) {
                                    $barang = Barang::find($state);
                                    if ($barang) {
                                        $set('satuan', $barang->satuan ?? 'Pcs');
                                    }
                                }
                            })
                            ->columnSpan(4),
                        Select::make('satuan')
                            ->label('Satuan')
                            ->options(function (Get $get) {
                                $barangId = $get('barang_id');
                                if (!$barangId) {
                                    return ['Pcs' => 'Pcs'];
                                }
                                $barang = Barang::find($barangId);
                                if (!$barang) {
                                    return ['Pcs' => 'Pcs'];
                                }
                                $opts = [];
                                foreach ($barang->getAvailableUnits() as $u) {
                                    $opts[$u['satuan']] = "{$u['satuan']} (L{$u['level']})";
                                }
                                return $opts;
                            })
                            ->required()
                            ->reactive()
                            ->columnSpan(2),
                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()->required()->minValue(1)
                            ->extraInputAttributes(['oninput' => "this.value = this.value.replace(/[^0-9]/g, '')"])
                            ->rule(fn (Get $get, $livewire) => $livewire instanceof \App\Filament\Resources\PerpindahanBarangs\Pages\CreatePerpindahanBarang
                                ? function (string $attribute, $value, Closure $fail) use ($get) {
                                    $gudangId = $get('../../gudang_asal_id');
                                    $barangId = $get('barang_id');
                                    if (! $gudangId || ! $barangId) {
                                        return;
                                    }
                                    $barang = Barang::find($barangId);
                                    $satuan = $get('satuan');
                                    $faktor = $barang ? $barang->getFaktorKonversi($satuan) : 1;
                                    $jumlahDasar = (int) $value * $faktor;

                                    $stok = (int) DB::table('barang_gudang')
                                        ->where('gudang_id', $gudangId)
                                        ->where('barang_id', $barangId)
                                        ->value('stok');
                                    if ($jumlahDasar > $stok) {
                                        $fail("Stok tidak cukup (tersedia: {$stok} " . ($barang->satuan ?? 'Pcs') . ", dibutuhkan: {$jumlahDasar}).");
                                    }
                                }
                                : null)
                            ->columnSpan(2),
                    ])
                    ->columns(8)
                    ->addActionLabel('+ Tambah Barang')
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
