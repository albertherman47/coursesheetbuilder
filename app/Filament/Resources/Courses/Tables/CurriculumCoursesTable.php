<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Models\AcademicYear;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurriculumCoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Tanév – legelső oszlop, jól látható badge-ként
                TextColumn::make('curriculum.academicYear.year_code')
                    ->label('Tanév ')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('course_code')
                    ->label('Kurzus kód')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_name_hu')
                    ->label('Kurzus neve')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('study_year')
                    ->label('Évfolyam')
                    ->sortable(),
                TextColumn::make('semester')
                    ->label('Félév')
                    ->sortable(),
                TextColumn::make('credits')
                    ->label('Kredit')
                    ->sortable(),


            ])
            ->filters([
                // Szűrés tanév szerint
                SelectFilter::make('curriculum_id')
                    ->label('Tanév')
                    ->options(function () {
                        return \App\Models\Curriculum::with('academicYear')
                            ->get()
                            ->pluck('academicYear.year_code', 'id')
                            ->filter()
                            ->sort()
                            ->toArray();
                    })
                    ->placeholder('Minden tanév'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
