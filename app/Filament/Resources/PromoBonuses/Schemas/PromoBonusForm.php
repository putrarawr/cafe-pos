<?php

namespace App\Filament\Resources\PromoBonuses\Schemas;

use App\Models\Barang;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PromoBonusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Utama Promo')
                    ->description('Atur nama promo dan status keaktifan promo.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nama_promo')
                                ->label('Nama Promo')
                                ->placeholder('Misal: Beli 10 Indomie Gratis 1 Susu Ultra')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_aktif')
                                ->label('Status Promo Aktif')
                                ->default(true)
                                ->inline(false),
                        ]),
                    ]),

                Section::make('Syarat Pembelian (Barang Utama)')
                    ->description('Tentukan barang dan jumlah minimal yang harus dibeli.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('barang_utama_id')
                                ->label('Barang Utama')
                                ->relationship('barangUtama', 'nama_barang')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $barang = Barang::find($state);
                                        if ($barang) {
                                            $set('satuan_utama', $barang->satuan);
                                        }
                                    }
                                }),
                            TextInput::make('min_qty_utama')
                                ->label('Minimal Qty Pembelian')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            Select::make('satuan_utama')
                                ->label('Satuan Pembelian')
                                ->options(function (Get $get) {
                                    $barangId = $get('barang_utama_id');
                                    if (! $barangId) {
                                        return [];
                                    }
                                    $barang = Barang::find($barangId);
                                    if (! $barang) {
                                        return [];
                                    }
                                    $units = $barang->getAvailableUnits();
                                    $options = [];
                                    foreach ($units as $u) {
                                        $options[$u['satuan']] = $u['satuan'];
                                    }

                                    return $options;
                                })
                                ->searchable()
                                ->nullable(),
                        ]),
                    ]),

                Section::make('Hadiah / Barang Bonus')
                    ->description('Tentukan barang bonus yang diberikan secara gratis.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('barang_bonus_id')
                                ->label('Barang Bonus / Gratis')
                                ->relationship('barangBonus', 'nama_barang')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $barang = Barang::find($state);
                                        if ($barang) {
                                            $set('satuan_bonus', $barang->satuan);
                                        }
                                    }
                                }),
                            TextInput::make('qty_bonus')
                                ->label('Qty Bonus / Gratis')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            Select::make('satuan_bonus')
                                ->label('Satuan Bonus')
                                ->options(function (Get $get) {
                                    $barangId = $get('barang_bonus_id');
                                    if (! $barangId) {
                                        return [];
                                    }
                                    $barang = Barang::find($barangId);
                                    if (! $barang) {
                                        return [];
                                    }
                                    $units = $barang->getAvailableUnits();
                                    $options = [];
                                    foreach ($units as $u) {
                                        $options[$u['satuan']] = $u['satuan'];
                                    }

                                    return $options;
                                })
                                ->searchable()
                                ->nullable(),
                        ]),
                    ]),

                Section::make('Masa Berlaku & Pengaturan Promo')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('tanggal_mulai')
                                ->label('Tanggal Mulai Promo')
                                ->default(now())
                                ->required(),
                            DatePicker::make('tanggal_selesai')
                                ->label('Tanggal Selesai Promo')
                                ->default(now()->addMonth())
                                ->required()
                                ->afterOrEqual('tanggal_mulai'),
                            Toggle::make('is_kelipatan')
                                ->label('Berlaku Kelipatan')
                                ->helperText('Jika aktif, beli 2x lipat dapat 2x bonus, dst.')
                                ->default(true)
                                ->inline(false),
                        ]),
                    ]),
            ]);
    }
}
