<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'super_admin' => 'Super Admin',
                        'department_admin' => 'Department Admin',
                        'teacher' => 'Teacher',
                        default => ucfirst(str_replace('_', ' ', $state))
                    })
                    ->color(fn (string $state): string => match($state) {
                        'super_admin' => 'danger',
                        'department_admin' => 'warning',
                        'teacher' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('email_verified_at')
                    ->label('Email Verified')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
