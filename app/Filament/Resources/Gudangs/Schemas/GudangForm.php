<?php

namespace App\Filament\Resources\Gudangs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GudangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_gudang')
                    ->label('Nama Gudang')
                    ->required()
                    ->maxLength(255),
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
