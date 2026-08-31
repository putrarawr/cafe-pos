<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_supplier')
                    ->required()
                    ->maxLength(255),
                TextInput::make('no_telepon')
                    ->numeric()
                    ->required(),
                Textarea::make('alamat')
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->required()
                    ->default('aktif'),
            ]);
    }
}
