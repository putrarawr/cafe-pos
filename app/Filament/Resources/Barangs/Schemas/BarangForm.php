<?php

namespace App\Filament\Resources\Barangs\Schemas;

use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Dasar Barang')
                    ->description('Data utama barang dan harga eceran dasar (Level 1)')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('jenis_barang_id')
                                ->label('Jenis Barang')
                                ->relationship('jenisBarang', 'nama_jenis')
                                ->required()
                                ->searchable()
                                ->preload(),
                            TextInput::make('nama_barang')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('nomer_seri')
                                ->label('Nomor Seri')
                                ->placeholder('Otomatis digenerate saat simpan (misal: ROK-0001)')
                                ->disabled()
                                ->dehydrated(false)
                                ->visible(fn ($record) => filled($record?->nomer_seri)),
                            TextInput::make('barcode')
                                ->label('Barcode Produk (Opsional)')
                                ->placeholder('Kosongkan untuk otomatis menggunakan Nomor Seri')
                                ->helperText('Jika barang tidak punya barcode pabrik, otomatis disamakan dengan Nomor Seri.')
                                ->maxLength(255),
                            TextInput::make('satuan')
                                ->label('Satuan Terkecil / Dasar (Level 1)')
                                ->placeholder('Misal: Pcs, Batang, Botol, Saset')
                                ->default('Pcs')
                                ->required(),
                            TextInput::make('harga_beli')
                                ->label('Harga Beli Terakhir (Level 1)')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0),
                            TextInput::make('hpp')
                                ->label('HPP Average (Rata-Rata Tertimbang)')
                                ->numeric()
                                ->prefix('Rp')
                                ->readOnly()
                                ->helperText('Otomatis dihitung ulang secara akurat saat ada transaksi Pembelian baru.')
                                ->default(0),
                            TextInput::make('harga_jual')
                                ->label('Harga Jual Eceran (Level 1)')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0),
                        ]),
                    ]),

                Section::make('Harga Jual Bertingkat (3 Level Quantity)')
                    ->description('Opsional: Atur potongan harga bertingkat berdasarkan kuantitas minimal pembelian.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tipe_harga_bertingkat')
                            ->label('Tipe Potongan Tier')
                            ->options([
                                'persen' => 'Persentase Diskon (%)',
                                'nominal' => 'Nominal Harga Jual per Unit (Rp)',
                            ])
                            ->default('persen')
                            ->required(),

                        Grid::make(3)->schema([
                            Section::make('Tier Level 1')
                                ->schema([
                                    TextInput::make('min_qty_1')
                                        ->label('Minimal Qty Tier 1')
                                        ->numeric()
                                        ->default(1)
                                        ->required(),
                                    TextInput::make('nilai_tier_1')
                                        ->label('Nilai Tier 1 (% atau Rp)')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Isi 0 jika tidak ada diskon di Tier 1'),
                                ]),

                            Section::make('Tier Level 2')
                                ->schema([
                                    TextInput::make('min_qty_2')
                                        ->label('Minimal Qty Tier 2')
                                        ->numeric()
                                        ->placeholder('Misal: 6'),
                                    TextInput::make('nilai_tier_2')
                                        ->label('Nilai Tier 2 (% atau Rp)')
                                        ->numeric()
                                        ->default(0),
                                ]),

                            Section::make('Tier Level 3')
                                ->schema([
                                    TextInput::make('min_qty_3')
                                        ->label('Minimal Qty Tier 3')
                                        ->numeric()
                                        ->placeholder('Misal: 20'),
                                    TextInput::make('nilai_tier_3')
                                        ->label('Nilai Tier 3 (% atau Rp)')
                                        ->numeric()
                                        ->default(0),
                                ]),
                        ]),
                    ]),

                Section::make('Tingkatan Satuan & Harga Grosir (Level 2 - 4)')
                    ->description('Opsional: Atur konversi satuan bertingkat terhadap Satuan Pertama (Level 1). Kosongkan jika produk hanya memiliki 1 satuan.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        // Level 2
                        Section::make('Satuan Level 2')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_2')
                                        ->label('Nama Satuan Level 2')
                                        ->placeholder('Misal: Pack, Renceng'),
                                    TextInput::make('isi_satuan_2')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 20'),
                                    TextInput::make('harga_beli_2')
                                        ->label('Harga Beli Level 2')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_2')
                                        ->label('Harga Jual Level 2')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),

                        // Level 3
                        Section::make('Satuan Level 3')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_3')
                                        ->label('Nama Satuan Level 3')
                                        ->placeholder('Misal: Slof, Box'),
                                    TextInput::make('isi_satuan_3')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 200'),
                                    TextInput::make('harga_beli_3')
                                        ->label('Harga Beli Level 3')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_3')
                                        ->label('Harga Jual Level 3')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),

                        // Level 4
                        Section::make('Satuan Level 4')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_4')
                                        ->label('Nama Satuan Level 4')
                                        ->placeholder('Misal: Bal, Karton'),
                                    TextInput::make('isi_satuan_4')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 2000'),
                                    TextInput::make('harga_beli_4')
                                        ->label('Harga Beli Level 4')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_4')
                                        ->label('Harga Jual Level 4')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
