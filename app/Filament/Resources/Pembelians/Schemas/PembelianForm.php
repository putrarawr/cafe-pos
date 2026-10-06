<?php

namespace App\Filament\Resources\Pembelians\Schemas;

use App\Models\Barang;
use App\Models\Gudang;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class PembelianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // === MASTER BELI ===
                TextInput::make('nomer_entry')
                    ->label('Nomer Entry')
                    ->default('Otomatis')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpan(1),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->required()
                    ->columnSpan(1),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'nama_supplier')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),
                Select::make('gudang_id')
                    ->label('Gudang Tujuan')
                    ->relationship('gudang', 'nama_gudang')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->columnSpan(1),
                Select::make('jenis_pembayaran')
                    ->label('Jenis Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer',
                    ])
                    ->required()
                    ->columnSpan(2),
                \Filament\Forms\Components\Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),

                // === DETAIL BELI ===
                Repeater::make('details')
                    ->label('Detail Barang')
                    ->relationship()
                    ->schema([
                        Select::make('barang_id')
                            ->label('Barang')
                            ->relationship(
                                name: 'barang',
                                titleAttribute: 'nama_barang',
                                modifyQueryUsing: fn ($query) => $query->with('jenisBarang')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Barang $record) => "{$record->nomer_seri} - {$record->nama_barang} ({$record->satuan})")
                            ->searchable(['nama_barang', 'nomer_seri', 'barcode'])
                            ->required()
                            ->preload()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $barang = Barang::find($state);
                                    if ($barang) {
                                        $satuanAwal = $barang->satuan ?? 'Pcs';
                                        $set('satuan', $satuanAwal);
                                        $set('harga', $barang->getHargaBeliForSatuan($satuanAwal));
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
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $barangId = $get('barang_id');
                                if ($barangId && $state) {
                                    $barang = Barang::find($barangId);
                                    if ($barang) {
                                        $set('harga', $barang->getHargaBeliForSatuan($state));
                                        self::hitungTotal($get, $set);
                                    }
                                }
                            })
                            ->columnSpan(2),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->extraInputAttributes(['oninput' => "this.value = this.value.replace(/[^0-9]/g, '')"])
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::hitungTotal($get, $set);
                            })
                            ->columnSpan(2),
                        TextInput::make('harga')
                            ->label('Harga')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('Rp')
                            ->extraInputAttributes(['oninput' => "this.value = this.value.replace(/[^0-9]/g, '')"])
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::hitungTotal($get, $set);
                            })
                            ->columnSpan(2),
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->readOnly()
                            ->columnSpan(2),

                        Placeholder::make('peringatan_stok_maksimum')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(function (Get $get) {
                                $gudangId = $get('../../gudang_id');
                                $barangId = $get('barang_id');
                                $jumlah = (int) $get('jumlah');
                                $satuan = $get('satuan');

                                if (! $gudangId || ! $barangId || $jumlah <= 0) {
                                    return false;
                                }

                                $check = PembelianForm::cekOverstockItem($barangId, $satuan, $jumlah, $gudangId);
                                return $check['is_overstock'];
                            })
                            ->content(function (Get $get) {
                                $gudangId = $get('../../gudang_id');
                                $barangId = $get('barang_id');
                                $jumlah = (int) $get('jumlah');
                                $satuan = $get('satuan');

                                $check = PembelianForm::cekOverstockItem($barangId, $satuan, $jumlah, $gudangId);
                                if (! $check['is_overstock']) {
                                    return '';
                                }

                                return new HtmlString('
                                    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 6px; padding: 10px 14px; display: flex; align-items: flex-start; gap: 10px; color: #fbbf24; font-size: 13px;">
                                        <svg style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <div>
                                            <strong style="color: #fde68a;">Peringatan Stok Maksimum:</strong> ' . e($check['pesan']) . '
                                        </div>
                                    </div>
                                ');
                            }),
                    ])
                    ->columns(12)
                    ->addActionLabel('+ Tambah Barang')
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::hitungTotalGlobal($get, $set);
                    })
                    ->columnSpanFull(),

                // === TOTAL ===
                TextInput::make('total')
                    ->label('Total Pembelian')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->readOnly()
                    ->columnSpan(3),
            ])
            ->columns(3);
    }

    public static function cekOverstockItem($barangId, $satuan, $jumlah, $gudangId): array
    {
        $barang = Barang::find($barangId);
        if (! $barang) {
            return ['is_overstock' => false];
        }

        $faktor = $barang->getFaktorKonversi($satuan);
        $qtyDasarBeli = (int) $jumlah * $faktor;

        $pivot = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->first();

        $stokSaatIni = (int) ($pivot->stok ?? 0);
        $stokMaksimum = (int) ($pivot->stok_maksimum ?? 0);

        if ($stokMaksimum <= 0) {
            return ['is_overstock' => false];
        }

        $totalAkanMenjadi = $stokSaatIni + $qtyDasarBeli;

        if ($totalAkanMenjadi > $stokMaksimum) {
            $gudang = Gudang::find($gudangId);
            $namaGudang = $gudang ? $gudang->nama_gudang : 'Gudang Tujuan';
            $maksFmt = $barang->formatStokBerantai($stokMaksimum);
            $totalFmt = $barang->formatStokBerantai($totalAkanMenjadi);

            $pesan = "Jumlah pembelian melebihi batas stok maksimum di {$namaGudang} (Maks: {$maksFmt}, Total akan menjadi: {$totalFmt}).";

            return [
                'is_overstock' => true,
                'stok_saat_ini' => $stokSaatIni,
                'qty_beli_dasar' => $qtyDasarBeli,
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
        $gudangId = $state['gudang_id'] ?? null;
        $details = $state['details'] ?? [];
        if (! $gudangId || empty($details)) {
            return [];
        }

        $overstocks = [];
        foreach ($details as $detail) {
            $barangId = $detail['barang_id'] ?? null;
            $jumlah = (int) ($detail['jumlah'] ?? 0);
            $satuan = $detail['satuan'] ?? null;

            if ($barangId && $jumlah > 0) {
                $check = static::cekOverstockItem($barangId, $satuan, $jumlah, $gudangId);
                if ($check['is_overstock']) {
                    $overstocks[] = $check;
                }
            }
        }

        return $overstocks;
    }

    private static function hitungTotal(Get $get, Set $set): void
    {
        $jumlah = (int) $get('jumlah');
        $harga = (int) $get('harga');
        
        $subtotal = $jumlah * $harga;
        $set('subtotal', $subtotal);

        // Ambil semua details dari parent
        $details = $get('../../details') ?? [];
        $total = 0;
        foreach ($details as $k => $detail) {
            $j = (int) ($detail['jumlah'] ?? 0);
            $h = (int) ($detail['harga'] ?? 0);
            $total += $j * $h;
        }
        $set('../../total', $total);
    }

    private static function hitungTotalGlobal(Get $get, Set $set): void
    {
        $details = $get('details') ?? [];
        $total = 0;
        foreach ($details as $detail) {
            $j = (int) ($detail['jumlah'] ?? 0);
            $h = (int) ($detail['harga'] ?? 0);
            $total += $j * $h;
        }
        $set('total', $total);
    }
}
