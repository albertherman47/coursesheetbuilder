<?php

namespace App\Filament\Resources\Programs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProgramInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('department.name_hu')
                    ->label('Tanszék'),
                TextEntry::make('code'),
                TextEntry::make('name_hu'),
                TextEntry::make('name_ro'),
                TextEntry::make('name_en'),
                TextEntry::make('domain')
                    ->placeholder('-')
                    ->suffix('szint'),
                TextEntry::make('qualification')
                    ->placeholder('-')
                    ->suffix('fokozat'),
                TextEntry::make('coordinator.full_name')
                    ->label('Coordinator full name')
                    ->placeholder('-'), 


                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
