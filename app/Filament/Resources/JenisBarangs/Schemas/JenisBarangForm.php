<?php

namespace App\Filament\Resources\JenisBarangs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class JenisBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_jenis')
                    ->label('Nama Jenis')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if (blank($get('kode_jenis'))) {
                            $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $state ?? ''));
                            $set('kode_jenis', substr($clean, 0, 3));
                        }
                    }),
                TextInput::make('kode_jenis')
                    ->label('Kode Prefix (Contoh: ROK / RKK)')
                    ->placeholder('Otomatis dari 3 huruf pertama nama jenis')
                    ->helperText('Digunakan untuk awalan Nomor Seri barang (misal: ROK-0001)')
                    ->maxLength(10)
                    ->dehydrateStateUsing(fn($state) => filled($state) ? strtoupper(trim($state)) : null),
                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
