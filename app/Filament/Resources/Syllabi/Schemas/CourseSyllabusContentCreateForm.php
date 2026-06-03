<?php

namespace App\Filament\Resources\Syllabi\Schemas;

use App\Models\CourseAssignment;
use App\Services\CourseSyllabusFormBuilder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CourseSyllabusContentCreateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Új tantárgyi adatlap')
                    ->description('Válaszd ki a tantárgyat. A kitöltés a szerkesztő varázslóban folytatódik a létrehozás után.')
                    ->schema([
                        Placeholder::make('no_assignments_hint')
                            ->hiddenLabel()
                            ->content('Jelenleg nincs olyan tantárgy, amelyhez még nem készült adatlap, és amelyhez hozzáférsz. Ellenőrizd a feladatkiosztást, vagy kérj admin segítséget.')
                            ->visible(fn (): bool => self::assignmentsAvailableCount() === 0),

                        Select::make('course_assignment_id')
                            ->label('Tantárgy')
                            ->options(fn (): array => self::assignmentOptions())
                            ->getSearchResultsUsing(fn (string $search): array => self::searchAssignmentOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::assignmentLabel(is_numeric($value) ? (int) $value : null))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->hidden(fn (): bool => self::assignmentsAvailableCount() === 0),

                        Placeholder::make('template_info')
                            ->label('Használt sablon')
                            ->content(function (Get $get): string {
                                $assignmentId = $get('course_assignment_id');

                                if (! $assignmentId) {
                                    return 'Válassz tantárgyat a sablon adatainak megjelenítéséhez.';
                                }

                                $assignment = CourseAssignment::query()
                                    ->with(['curriculumCourse.curriculum.academicYear', 'curriculumCourse.curriculum.program'])
                                    ->find($assignmentId);

                                if (! $assignment) {
                                    return 'A kiválasztott hozzárendelés nem található.';
                                }

                                try {
                                    $template = (new CourseSyllabusFormBuilder)->resolveActiveTemplate($assignment);
                                    $year = $assignment->curriculumCourse->curriculum->academicYear->year_code ?? '—';
                                    $program = $assignment->curriculumCourse->curriculum->program->name_hu ?? '—';

                                    return "«{$template->name}» · tanév: {$year} · szak: {$program}";
                                } catch (\Throwable) {
                                    return 'Nincs aktív sablon ehhez a tanévhez. Hozz létre vagy aktiválj egy sablont a Tantárgyi adatlap sablonok menüben.';
                                }
                            })
                            ->visible(fn (Get $get): bool => filled($get('course_assignment_id'))),
                    ]),
            ]);
    }

    public static function assignmentsAvailableCount(): int
    {
        return self::assignmentQuery()->count();
    }

    /**
     * @return array<int, string>
     */
    public static function assignmentOptions(): array
    {
        return self::assignmentQuery()
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (CourseAssignment $assignment) => [
                $assignment->id => self::formatAssignmentLabel($assignment),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchAssignmentOptions(string $search): array
    {
        $term = '%'.mb_strtolower($search).'%';

        return self::assignmentQuery()
            ->where(function (Builder $query) use ($term) {
                $query
                    ->whereHas('curriculumCourse', function (Builder $courseQuery) use ($term) {
                        $courseQuery
                            ->whereRaw('LOWER(course_name_hu) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(course_name_ro) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(course_code) LIKE ?', [$term]);
                    })
                    ->orWhereHas('curriculumCourse.curriculum.program', function (Builder $programQuery) use ($term) {
                        $programQuery->whereRaw('LOWER(name_hu) LIKE ?', [$term]);
                    })
                    ->orWhereHas('curriculumCourse.curriculum.academicYear', function (Builder $yearQuery) use ($term) {
                        $yearQuery->whereRaw('LOWER(year_code) LIKE ?', [$term]);
                    });
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (CourseAssignment $assignment) => [
                $assignment->id => self::formatAssignmentLabel($assignment),
            ])
            ->all();
    }

    public static function assignmentLabel(?int $assignmentId): ?string
    {
        if (! $assignmentId) {
            return null;
        }

        $assignment = CourseAssignment::query()
            ->with(['curriculumCourse.curriculum.program', 'curriculumCourse.curriculum.academicYear'])
            ->find($assignmentId);

        return $assignment ? self::formatAssignmentLabel($assignment) : null;
    }

    public static function formatAssignmentLabel(CourseAssignment $assignment): string
    {
        $course = $assignment->curriculumCourse;
        $year = $course?->curriculum?->academicYear?->year_code ?? '—';
        $program = $course?->curriculum?->program?->name_hu ?? '—';
        $name = $course?->course_name_hu ?? '—';
        $code = $course?->course_code ?? '—';

        return "{$year} · {$program} · {$name} ({$code})";
    }

    public static function assignmentQuery(): Builder
    {
        $query = CourseAssignment::query()
            ->with(['curriculumCourse.curriculum.program', 'curriculumCourse.curriculum.academicYear'])
            ->whereDoesntHave('syllabusContent')
            ->whereHas('curriculumCourse.curriculum.academicYear')
            ->orderByDesc(
                \App\Models\AcademicYear::query()
                    ->select('year_code')
                    ->join('curricula', 'curricula.academic_year_id', '=', 'academic_years.id')
                    ->join('curriculum_courses', 'curriculum_courses.curriculum_id', '=', 'curricula.id')
                    ->whereColumn('curriculum_courses.id', 'course_assignments.curriculum_course_id')
                    ->limit(1)
            );

        $user = auth()->user();

        if ($user && $user->hasRole('teacher')) {
            $teacher = $user->teacher;

            if ($teacher) {
                $query->where(function (Builder $leaderQuery) use ($teacher) {
                    $leaderQuery
                        ->where('course_leader_id', $teacher->id)
                        ->orWhere('seminar_leader_id', $teacher->id)
                        ->orWhere('lab_leader_id', $teacher->id)
                        ->orWhere('project_leader_id', $teacher->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }
}
