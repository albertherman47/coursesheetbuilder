<?php

namespace App\Filament\Resources\CourseAssignments\Schemas;

use App\Models\CurriculumCourse;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseAssignmentForm
{
    /**
     * Tantárgy és oktatói hozzárendelések szerkesztő űrlap.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([



                Section::make('Tantárgy információk')
                    ->schema([

                        Placeholder::make('academic_year')
                            ->label('Tanév')
                            ->content(fn ($record) => $record?->curriculum?->academicYear?->year_code ?? '-'),

                        Placeholder::make('curriculum_name')
                            ->label('Tanterv')
                            ->content(fn ($record) => $record?->curriculum?->name ?? '-'),

                        Placeholder::make('program_name')
                            ->label('Szak')
                            ->content(fn ($record) => $record?->curriculum?->program?->name_hu ?? '-'),

                        Placeholder::make('course_code')
                            ->label('Kurzus kód')
                            ->content(fn ($record) => $record?->course_code ?? '-'),

                        Placeholder::make('course_name_hu')
                            ->label('Kurzus neve')
                            ->content(fn ($record) => $record?->course_name_hu ?? '-'),

                        Placeholder::make('study_year')
                            ->label('Évfolyam')
                            ->content(fn ($record) => $record?->study_year ?? '-'),

                        Placeholder::make('semester')
                            ->label('Félév')
                            ->content(fn ($record) => $record?->semester ?? '-'),



                    ])
                    ->columns(2)
                    ->collapsible()
                    ->extraAttributes([
                        'class' => 'border-2 border-primary-500 rounded-xl bg-gray-50 dark:bg-gray-900',
                    ]),



                Section::make('Oktatói hozzárendelések')
                    ->schema([

                        Repeater::make('courseAssignments')
                            ->relationship('courseAssignments')
                            ->label('Hozzárendelt oktatók')
                            ->schema([

                                Select::make('course_leader_id')
                                    ->relationship('courseLeader', 'last_name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record) => $record->full_name
                                    )
                                    ->label('Előadó')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Select::make('seminar_leader_id')
                                    ->relationship('seminarLeader', 'last_name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record) => $record->full_name
                                    )
                                    ->label('Szemináriumvezető')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Select::make('lab_leader_id')
                                    ->relationship('labLeader', 'last_name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record) => $record->full_name
                                    )
                                    ->label('Laborvezető')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Select::make('project_leader_id')
                                    ->relationship('projectLeader', 'last_name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record) => $record->full_name
                                    )
                                    ->label('Projektvezető')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                            ])
                            ->maxItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull(),

                    ])
                    ->extraAttributes([
                        'class' => 'border-2 border-success-500 rounded-xl bg-white dark:bg-gray-900',
                    ]),
            ]);
    }
}
