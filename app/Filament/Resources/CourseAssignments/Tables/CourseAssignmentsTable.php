<?php

namespace App\Filament\Resources\CourseAssignments\Tables;

use App\Models\AcademicYear;
use App\Models\Program;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Oktató-tantárgy hozzárendelések listázó táblázatának sémája.
 *
 * Megjeleníti a tantárgyakat szak, kód, név, félév és hozzárendelt előadó
 * szerint. Szűrhető szak szerint (program_id). Minden soron szerkesztés
 * gomb is található ("Tanárok hozzárendelése" címkével).
 */
class CourseAssignmentsTable
{
    /**
     * Konfigürálja a tantárgy-hozzárendelés listázó táblázatot.
     * Tartalmaz program-szűrőt és szerkesztés akciót.
     *
     * @param Table $table A táblázat példánya.
     * @return Table A konfigurált táblázat.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Keresés: kód, név, szak, oktató…')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->with(['curriculum.program', 'curriculum.academicYear', 'courseAssignments.courseLeader'])
                    ->orderByDesc(
                        AcademicYear::query()
                            ->select('year_code')
                            ->join('curricula', 'curricula.academic_year_id', '=', 'academic_years.id')
                            ->whereColumn('curricula.id', 'curriculum_courses.curriculum_id')
                            ->limit(1)
                    )
                    ->orderBy('curriculum_courses.course_code');
            })
            ->columns([
                TextColumn::make('curriculum.academicYear.year_code')
                    ->label('Tanév')
                    ->badge()
                    ->color('info')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            AcademicYear::query()
                                ->select('year_code')
                                ->join('curricula', 'curricula.academic_year_id', '=', 'academic_years.id')
                                ->whereColumn('curricula.id', 'curriculum_courses.curriculum_id')
                                ->limit(1),
                            $direction
                        );
                    }),
                TextColumn::make('curriculum.program.name_hu')
                    ->label('Szak')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%'.Str::lower($search).'%';

                        return $query->whereHas('curriculum.program', function (Builder $programQuery) use ($term) {
                            $programQuery->whereRaw('LOWER(name_hu) LIKE ?', [$term]);
                        });
                    }),
                TextColumn::make('course_code')
                    ->label('Kód')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_name_hu')
                    ->label('Tantárgy')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%'.Str::lower($search).'%';

                        return $query->where(function (Builder $courseQuery) use ($term) {
                            $courseQuery
                                ->whereRaw('LOWER(course_name_hu) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(course_name_ro) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(course_name_en) LIKE ?', [$term]);
                        });
                    })
                    ->sortable(),
                TextColumn::make('course_leader_display')
                    ->label('Előadó')
                    ->state(function ($record): string {
                        $leader = $record->courseAssignments->first()?->courseLeader;

                        return $leader?->full_name ?? '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%'.Str::lower($search).'%';

                        return $query->whereHas('courseAssignments', function (Builder $assignmentQuery) use ($term) {
                            $assignmentQuery->where(function (Builder $leaderQuery) use ($term) {
                                $leaderQuery
                                    ->whereHas('courseLeader', function (Builder $teacherQuery) use ($term) {
                                        $teacherQuery->where(function (Builder $nameQuery) use ($term) {
                                            $nameQuery
                                                ->whereRaw('LOWER(first_name) LIKE ?', [$term])
                                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]);
                                        });
                                    })
                                    ->orWhereHas('seminarLeader', function (Builder $teacherQuery) use ($term) {
                                        $teacherQuery->where(function (Builder $nameQuery) use ($term) {
                                            $nameQuery
                                                ->whereRaw('LOWER(first_name) LIKE ?', [$term])
                                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]);
                                        });
                                    })
                                    ->orWhereHas('labLeader', function (Builder $teacherQuery) use ($term) {
                                        $teacherQuery->where(function (Builder $nameQuery) use ($term) {
                                            $nameQuery
                                                ->whereRaw('LOWER(first_name) LIKE ?', [$term])
                                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]);
                                        });
                                    });
                            });
                        });
                    }),
            ])
            ->filters([
                SelectFilter::make('program_id')
                    ->label('Szak (Program)')
                    ->options(Program::pluck('name_hu', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            $query->whereHas('curriculum', function ($q) use ($data) {
                                $q->where('program_id', $data['value']);
                            });
                        }
                        return $query;
                    }),
                SelectFilter::make('academic_year_id')
                    ->label('Tanév')
                    ->options(AcademicYear::pluck('year_code', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            $query->whereHas('curriculum', function ($q) use ($data) {
                                $q->where('academic_year_id', $data['value']);
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Tanárok hozzárendelése')
                    ->icon('heroicon-m-users'),
            ]);
    }
}
