<?php

namespace App\Filament\Resources\Mejas\Schemas;

use App\Models\Gudang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MejaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Meja')
                    ->description('Meja hanya dipakai untuk pesanan dine in. Take away dan delivery tidak memakai meja.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('kode_meja')
                                ->label('Kode Meja')
                                ->placeholder('Contoh: Meja 01, Meja 02')
                                ->helperText('Boleh sama antar cabang, asal unik di dalam satu cabang.')
                                ->required()
                                ->maxLength(50)
                                ->dehydrateStateUsing(fn ($state) => trim($state)),

                            TextInput::make('nama_meja')
                                ->label('Nama / Area')
                                ->placeholder('Contoh: Indoor, Teras, VIP')
                                ->helperText('Tampil sebagai keterangan di bawah kode meja.')
                                ->required()
                                ->maxLength(100),

                            Select::make('gudang_id')
                                ->label('Gudang / Cabang')
                                ->options(fn () => Gudang::orderBy('nama_gudang')->pluck('nama_gudang', 'id')->all())
                                ->required()
                                ->searchable()
                                ->native(false),

                            TextInput::make('area')
                                ->label('Area / Ruangan')
                                ->placeholder('Contoh: Indoor, Teras, VIP')
                                ->maxLength(255),
                        ]),
                    ]),
                Section::make('Kapasitas & Durasi')
                    ->description('Durasi menjadi batas pemakaian normal. Kalau meja lewat durasi, ditandai overstay di dashboard kasir.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('kapasitas')
                                ->label('Kapasitas (Orang)')
                                ->numeric()
                                ->minValue(1)
                                ->default(4)
                                ->required(),

                            TextInput::make('durasi_menit')
                                ->label('Durasi (Menit)')
                                ->numeric()
                                ->minValue(1)
                                ->default(60)
                                ->required()
                                ->helperText('Batas sebelum meja ditandai overstay.'),

                            Toggle::make('status_aktif')
                                ->label('Status Aktif')
                                ->helperText('Meja nonaktif tidak bisa dipilih di dashboard kasir (mis. sedang dibersihkan / rusak).')
                                ->default(true),
                        ]),
                    ]),
            ]);
    }
}
