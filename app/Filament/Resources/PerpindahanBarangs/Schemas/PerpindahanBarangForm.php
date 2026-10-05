<?php

namespace App\Filament\Resources\PerpindahanBarangs\Schemas;

use App\Models\Barang;
use App\Models\Gudang;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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
                            ->reactive()
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

                        Placeholder::make('peringatan_stok_maksimum')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(function (Get $get) {
                                $gudangTujuanId = $get('../../gudang_tujuan_id');
                                $barangId = $get('barang_id');
                                $jumlah = (int) $get('jumlah');
                                $satuan = $get('satuan');

                                if (! $gudangTujuanId || ! $barangId || $jumlah <= 0) {
                                    return false;
                                }

                                $check = PerpindahanBarangForm::cekOverstockTujuan($barangId, $satuan, $jumlah, $gudangTujuanId);
                                return $check['is_overstock'];
                            })
                            ->content(function (Get $get) {
                                $gudangTujuanId = $get('../../gudang_tujuan_id');
                                $barangId = $get('barang_id');
                                $jumlah = (int) $get('jumlah');
                                $satuan = $get('satuan');

                                $check = PerpindahanBarangForm::cekOverstockTujuan($barangId, $satuan, $jumlah, $gudangTujuanId);
                                if (! $check['is_overstock']) {
                                    return '';
                                }

                                return new HtmlString('
                                    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 6px; padding: 10px 14px; display: flex; align-items: flex-start; gap: 10px; color: #fbbf24; font-size: 13px;">
                                        <svg style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <div>
                                            <strong style="color: #fde68a;">Peringatan Kapasitas Gudang:</strong> ' . e($check['pesan']) . '
                                        </div>
                                    </div>
                                ');
                            }),
                    ])
                    ->columns(8)
                    ->addActionLabel('+ Tambah Barang')
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function cekOverstockTujuan($barangId, $satuan, $jumlah, $gudangTujuanId): array
    {
        $barang = Barang::find($barangId);
        if (! $barang || ! $gudangTujuanId) {
            return ['is_overstock' => false];
        }

        $faktor = $barang->getFaktorKonversi($satuan);
        $qtyDasarPindah = (int) $jumlah * $faktor;

        $pivot = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangTujuanId)
            ->first();

        $stokSaatIni = (int) ($pivot->stok ?? 0);
        $stokMaksimum = (int) ($pivot->stok_maksimum ?? 0);

        if ($stokMaksimum <= 0) {
            return ['is_overstock' => false];
        }

        $totalAkanMenjadi = $stokSaatIni + $qtyDasarPindah;

        if ($totalAkanMenjadi > $stokMaksimum) {
            $gudang = Gudang::find($gudangTujuanId);
            $namaGudang = $gudang ? $gudang->nama_gudang : 'Gudang Tujuan';
            $maksFmt = $barang->formatStokBerantai($stokMaksimum);
            $totalFmt = $barang->formatStokBerantai($totalAkanMenjadi);

            $pesan = "Pemindahan ini melebihi kapasitas batas stok maksimum di {$namaGudang} (Maks: {$maksFmt}, Total akan menjadi: {$totalFmt}).";

            return [
                'is_overstock' => true,
                'stok_saat_ini' => $stokSaatIni,
                'qty_pindah_dasar' => $qtyDasarPindah,
                'total_akan_menjadi' => $totalAkanMenjadi,
                'stok_maksimum' => $stokMaksimum,
                'nama_barang' => $barang->nama_barang,
                'nama_gudang' => $namaGudang,
                'pesan' => $pesan,
            ];
        }

        return ['is_overstock' => false];
    }

    public static function getOverstockItemsFromState(array $state): array
    {
        $gudangTujuanId = $state['gudang_tujuan_id'] ?? null;
        $details = $state['details'] ?? [];
        if (! $gudangTujuanId || empty($details)) {
            return [];
        }

        $overstocks = [];
        foreach ($details as $detail) {
            $barangId = $detail['barang_id'] ?? null;
            $jumlah = (int) ($detail['jumlah'] ?? 0);
            $satuan = $detail['satuan'] ?? null;

            if ($barangId && $jumlah > 0) {
                $check = static::cekOverstockTujuan($barangId, $satuan, $jumlah, $gudangTujuanId);
                if ($check['is_overstock']) {
                    $overstocks[] = $check;
                }
            }
        }

        return $overstocks;
    }
}
