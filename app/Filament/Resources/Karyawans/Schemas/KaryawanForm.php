<?php

namespace App\Filament\Resources\Karyawans\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class KaryawanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_karyawan')
                    ->label('Nama Karyawan')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email (Username Kasir)')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                TextInput::make('no_telp')
                    ->label('No. Telepon')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->columnSpanFull(),
            ]);
    }
}
