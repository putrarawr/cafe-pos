<?php

namespace App\Filament\Resources\Pembelians\Schemas;

use App\Models\Barang;
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
