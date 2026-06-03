<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CurriculumCourseResource;
use App\Models\CourseAssignment;
use App\Models\CourseSyllabusContent;
use App\Models\SyllabusTemplate;
use App\Services\CourseSyllabusFormBuilder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Egy tantárgy részletes megtekintésének Filament oldalas (ViewRecord).
 *
 * A fejlécben egy "Create Syllabus" gomb jelenik meg, amely:
 * 1. Megkeresi a tantárgyhoz tartozó CourseAssignment rekordokat.
 * 2. Ellenőrzi, hogy létezik-e aktív SyllabusTemplate az adott tanévhez.
 * 3. Minden hozzárendeléshez létrehoz egy CourseSyllabusContent rekordot
 *    (ha még nem létezik), majd átirányít a szerkesztőre.
 */
class ViewCurriculumCourse extends ViewRecord
{
    protected static string $resource = CurriculumCourseResource::class;

    /**
     * A fejlécben megjelenő akciógombokat adja vissza.
     * Tartalmazza: Create Syllabus akció + alapértelmezett Edit akció.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_syllabus')
                ->label('Adatlapok Létrehozása')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->action(function () {
                    // Get course assignments for this curriculum course
                    $assignments = CourseAssignment::where('curriculum_course_id', $this->record->id)->get();

                    if ($assignments->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Nincs Tantárgy Hozzárendelés')
                            ->body('Nincs Tantárgy Hozzárendelés.')
                            ->send();
                        return;
                    }

                    // Get the active template for the academic year
                    $academicYearId = $this->record->curriculum->academic_year_id;
                    $template = SyllabusTemplate::where('academic_year_id', $academicYearId)
                        ->where('is_active', true)
                        ->first();

                    if (! $template) {
                        Notification::make()
                            ->danger()
                            ->title('Nincs aktív sablon')
                            ->body('A megadott tanévben nincs aktív adatlap sablon.')
                            ->send();

                        return;
                    }

                    if (! $template->hasValidPlaceholdersConfig()) {
                        Notification::make()
                            ->danger()
                            ->title('Hiányos sablon')
                            ->body('A sablon aktív, de nincs mezőstruktúrája (placeholders_config). Szerkeszd a sablont az adminban, vagy másold át a 2025/26-os sablon beállításait.')
                            ->send();

                        return;
                    }

                    // Create syllabus for each assignment that doesn't have one
                    $created = 0;
                    $builder = new CourseSyllabusFormBuilder();

                    foreach ($assignments as $assignment) {
                        $existing = CourseSyllabusContent::where('course_assignment_id', $assignment->id)->first();

                        if (! $existing) {
                            $builder->createDraftForAssignment($assignment);
                            $created++;
                        }
                    }

                    if ($created > 0) {
                        Notification::make()
                            ->success()
                            ->title('A tantárgyi adatlap sikeresen létrehozva.')
                            ->body("Létrehozva $created tantárgyi adatlap. Átirányítás a szerkesztőre...")
                            ->send();
                        // Redirect to edit the first created syllabus
                        $syllabusContent = CourseSyllabusContent::where('course_assignment_id', $assignments->first()->id)->first();
                        return redirect()->route('filament.admin.resources.syllabi.course-syllabus-contents.edit', $syllabusContent);
                    } else {
                        Notification::make()
                            ->info()
                            ->title('Már létezik a tantárgyi adatlap.')
                            ->body('A tantárgyi adatlap már létezik a megadott tanévben.')
                            ->send();
                    }
                }),
            Actions\EditAction::make(),
        ];
    }
}
