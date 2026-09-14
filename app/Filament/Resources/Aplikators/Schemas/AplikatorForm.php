<?php

namespace App\Filament\Resources\Aplikators\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AplikatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Platform / Aplikator Delivery')
                    ->description('Data channel penjualan delivery online dan estimasi potongan komisi platform')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nama_aplikator')
                                ->label('Nama Aplikator / Platform')
                                ->placeholder('Contoh: GoFood, GrabFood, ShopeeFood, Maxim')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('kode_aplikator')
                                ->label('Kode Singkat (Opsional)')
                                ->placeholder('Contoh: GOFOOD, GRAB')
                                ->maxLength(50)
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? strtoupper(trim($state)) : null),

                            TextInput::make('persentase_komisi')
                                ->label('Estimasi Komisi Platform (%)')
                                ->helperText('Potongan komisi dari platform (misal 20% untuk GoFood/GrabFood)')
                                ->numeric()
                                ->suffix('%')
                                ->default(0),

                            Toggle::make('status_aktif')
                                ->label('Status Aktif')
                                ->helperText('Aplikator aktif dan dapat digunakan pada penetapan harga barang')
                                ->default(true),

                            Textarea::make('keterangan')
                                ->label('Catatan / Keterangan')
                                ->placeholder('Informasi tambahan mengenai akun atau kerja sama platform...')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
